<?php

namespace Alexa;

/**
 * Verifies that an incoming request really came from Amazon Alexa.
 *
 * Alexa signs every request with a private key only Amazon holds. The matching
 * certificate chain is published at a URL carried in the
 * "SignatureCertChainUrl" header, and a base64 signature of the raw request
 * body is carried in the "Signature" header. Checking that signature is the
 * only thing that proves the caller is Amazon: the skill application IDs are
 * not secret and can be copied out of any request or the developer console.
 *
 * Two certificate locations are in use:
 *   https://sns.amazonaws.com/SimpleNotificationService-<id>.pem   (current)
 *   https://s3.amazonaws.com/echo.api/echo-api-cert-<id>.pem       (legacy)
 * Skills created before the SNS migration still receive the legacy form, and
 * those certificates are issued for "echo-api.amazon.com" rather than
 * "*.amazonaws.com".
 *
 * Throws SignatureException when the request cannot be trusted. Callers must
 * reject the request on any exception (fail closed).
 */
class SignatureException extends \RuntimeException
{
}

final class SignatureVerifier
{
	/** Hosts Amazon may serve the chain from. */
	const CERT_HOST_AMAZON = 'sns.amazonaws.com';
	const CERT_HOST_REGIONAL = '/^sns\.[a-z0-9-]+\.amazonaws\.com$/';
	const CERT_HOST_S3 = 's3.amazonaws.com';

	/** SNS serves its certificates from this path prefix. */
	const CERT_PATH_PREFIX = '/SimpleNotificationService-';

	/** Legacy Echo API certificates live under this exact S3 path. */
	const CERT_PATH_ECHO = '#^/echo\.api/echo-api-cert-[0-9A-Za-z._-]+\.pem$#';

	/** Limits for the certificate download. */
	const MAX_CHAIN_BYTES = 65536;
	const DOWNLOAD_TIMEOUT = 5;

	private $cacheDir;
	private $cacheTtl;

	/**
	 * @param string|null $cacheDir Where to cache fetched chains (null = temp dir)
	 * @param int|null    $cacheTtl Seconds a cached chain stays valid
	 */
	public function __construct($cacheDir = null, $cacheTtl = null)
	{
		if ($cacheTtl === null) {
			$cacheTtl = defined('ALEXA_CERT_CACHE_TTL') ? (int)ALEXA_CERT_CACHE_TTL : 86400;
		}
		$this->cacheTtl = max(0, (int)$cacheTtl);
		$this->cacheDir = ($cacheDir !== null) ? $cacheDir : sys_get_temp_dir() . '/alexa-signing-certs';
	}

	/**
	 * Full check: trust the URL, fetch the chain, trust the certificate and
	 * verify the signature over the raw body.
	 *
	 * @param string $rawBody         Exact bytes received (not re-encoded JSON)
	 * @param string $signatureHeader Value of the "Signature" header
	 * @param string $certChainUrl    Value of the "SignatureCertChainUrl" header
	 *
	 * @throws SignatureException
	 */
	public function verify($rawBody, $signatureHeader, $certChainUrl)
	{
		if (!is_string($rawBody) || $rawBody === '') {
			throw new SignatureException('Empty request body');
		}

		self::assertCertificateUrl($certChainUrl);
		$chain = $this->getCertificateChain($certChainUrl);
		$this->verifyWithChain($rawBody, $signatureHeader, $chain);
	}

	/**
	 * Verify a signature against a chain we already have. Split out from
	 * verify() so the crypto can be exercised without network access.
	 *
	 * @param string $rawBody
	 * @param string $signatureHeader
	 * @param string $chainPem
	 *
	 * @throws SignatureException
	 */
	public function verifyWithChain($rawBody, $signatureHeader, $chainPem)
	{
		$certificate = $this->assertCertificateIsAmazon($chainPem);
		$this->assertSignatureIsValid($rawBody, $signatureHeader, $certificate);
	}

	/**
	 * The chain URL comes from the request, so it is attacker controlled until
	 * proven otherwise. Only https on an Amazon SNS host will do.
	 *
	 * @throws SignatureException
	 */
	public static function assertCertificateUrl($url)
	{
		if (!is_string($url) || trim($url) === '') {
			throw new SignatureException('Missing SignatureCertChainUrl header');
		}

		$parts = parse_url($url);
		if ($parts === false || !isset($parts['scheme']) || !isset($parts['host'])) {
			throw new SignatureException('SignatureCertChainUrl is not a valid URL');
		}
		if (strtolower($parts['scheme']) !== 'https') {
			throw new SignatureException('SignatureCertChainUrl must use https');
		}
		if (isset($parts['port']) && (int)$parts['port'] !== 443) {
			throw new SignatureException('SignatureCertChainUrl must use port 443');
		}
		if (isset($parts['user']) || isset($parts['pass'])) {
			throw new SignatureException('SignatureCertChainUrl must not contain credentials');
		}

		$host = strtolower($parts['host']);
		$path = isset($parts['path']) ? $parts['path'] : '';

		// Current form: Amazon SNS publishes the chain.
		if ($host === self::CERT_HOST_AMAZON || preg_match(self::CERT_HOST_REGIONAL, $host)) {
			if (strpos($path, self::CERT_PATH_PREFIX) !== 0 || substr($path, -4) !== '.pem') {
				throw new SignatureException('SignatureCertChainUrl is not an SNS certificate path');
			}
			return;
		}

		// Legacy form: skills created before the SNS migration still receive Echo
		// API certificates from the S3 "echo.api" location. The path is pinned
		// exactly, so this cannot be pointed at any other S3 object.
		if ($host === self::CERT_HOST_S3) {
			if (!preg_match(self::CERT_PATH_ECHO, $path)) {
				throw new SignatureException('SignatureCertChainUrl is not an Echo API certificate path');
			}
			return;
		}

		throw new SignatureException('SignatureCertChainUrl host is not an Amazon certificate host');
	}

	/**
	 * Fetch (or reuse a cached copy of) the certificate chain.
	 *
	 * @return string PEM chain
	 * @throws SignatureException
	 */
	private function getCertificateChain($url)
	{
		$cached = $this->readCache($url);
		if ($cached !== null) {
			return $cached;
		}

		$pem = $this->download($url);
		if (strlen($pem) > self::MAX_CHAIN_BYTES) {
			throw new SignatureException('Certificate chain response is too large');
		}
		if (strpos(ltrim($pem), '-----BEGIN CERTIFICATE-----') !== 0) {
			throw new SignatureException('Certificate chain response is not a PEM certificate');
		}

		$this->writeCache($url, $pem);
		return $pem;
	}

	/**
	 * @return string
	 * @throws SignatureException
	 */
	private function download($url)
	{
		if (function_exists('curl_init')) {
			list($body, $errno, $status) = $this->curlFetch($url, null);

			// 60 = "SSL certificate problem", i.e. PHP's curl has no CA store
			// configured. Retry with the bundle the rest of this app already ships
			// rather than fail every request.
			if ($errno === 60) {
				$bundle = __DIR__ . '/../Spotify/cacert.pem';
				if (is_readable($bundle)) {
					list($body, $errno, $status) = $this->curlFetch($url, $bundle);
				}
			}

			if ($errno !== 0 || $body === false) {
				throw new SignatureException('Could not download the certificate chain (curl error ' . $errno . ')');
			}
			if ($status !== 200) {
				throw new SignatureException('Certificate chain download returned HTTP ' . $status);
			}
			return (string)$body;
		}

		$https = array('verify_peer' => true, 'verify_peer_name' => true, 'timeout' => self::DOWNLOAD_TIMEOUT);
		$bundle = __DIR__ . '/../Spotify/cacert.pem';
		if (is_readable($bundle)) {
			$https['cafile'] = $bundle;
		}
		$body = @file_get_contents($url, false, stream_context_create(array('https' => $https)), 0, self::MAX_CHAIN_BYTES + 1);
		if ($body === false) {
			throw new SignatureException('Could not download the certificate chain');
		}
		return $body;
	}

	/**
	 * @param string      $url
	 * @param string|null $cainfo PEM bundle to trust, or null for the default store
	 *
	 * @return array array($body, $errno, $httpStatus)
	 */
	private function curlFetch($url, $cainfo)
	{
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::DOWNLOAD_TIMEOUT);
		curl_setopt($ch, CURLOPT_TIMEOUT, self::DOWNLOAD_TIMEOUT);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_USERAGENT, 'dsh-alexa-verifier/1.0');
		if ($cainfo !== null) {
			curl_setopt($ch, CURLOPT_CAINFO, $cainfo);
		}
		$body = curl_exec($ch);
		$errno = curl_errno($ch);
		$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		return array($body, $errno, $status);
	}

	/**
	 * Confirm the leaf certificate is a live, Amazon issued certificate and that
	 * the chain is internally consistent.
	 *
	 * @return resource|\OpenSSLCertificate leaf certificate
	 * @throws SignatureException
	 */
	private function assertCertificateIsAmazon($chainPem)
	{
		$blocks = self::splitCertificates($chainPem);
		if (count($blocks) === 0) {
			throw new SignatureException('No certificate found in chain');
		}

		$leafPem = self::toPem($blocks[0]);
		$leaf = @openssl_x509_read($leafPem);
		if ($leaf === false) {
			throw new SignatureException('Could not parse the signing certificate');
		}

		$info = @openssl_x509_parse($leaf);
		if (!is_array($info) || !isset($info['validFrom_time_t']) || !isset($info['validTo_time_t'])) {
			throw new SignatureException('Could not read the signing certificate');
		}

		$now = time();
		$slack = 60; // tolerate small clock differences between us and Amazon
		if ($now + $slack < $info['validFrom_time_t']) {
			throw new SignatureException('Signing certificate is not valid yet');
		}
		if ($now - $slack > $info['validTo_time_t']) {
			throw new SignatureException('Signing certificate has expired');
		}

		if (!self::certificateCoversAmazonHost($info)) {
			throw new SignatureException('Signing certificate is not for an Amazon host');
		}

		// If the chain carries the issuer, make sure it really did sign the leaf
		// (catches a truncated or tampered chain).
		if (count($blocks) > 1 && function_exists('openssl_x509_verify')) {
			$issuer = @openssl_x509_read(self::toPem($blocks[1]));
			if ($issuer !== false && @openssl_x509_verify($leaf, $issuer) !== 1) {
				throw new SignatureException('Signing certificate chain is not consistent');
			}
		}

		return $leaf;
	}

	/**
	 * @param array $info openssl_x509_parse() output
	 * @return bool
	 */
	private static function certificateCoversAmazonHost(array $info)
	{
		$names = array();

		if (isset($info['extensions']['subjectAltName'])) {
			if (preg_match_all('/DNS:([^,\s]+)/', $info['extensions']['subjectAltName'], $matches)) {
				foreach ($matches[1] as $name) {
					$names[] = strtolower($name);
				}
			}
		}
		if (isset($info['subject']['CN'])) {
			$names[] = strtolower($info['subject']['CN']);
		}

		foreach ($names as $name) {
			$name = strtolower(trim($name));

			// Legacy Echo API certificates are issued for echo-api.amazon.com.
			if ($name === 'echo-api.amazon.com') {
				return true;
			}

			// Amazon's SNS certificates are issued under *.amazonaws.com (the exact
			// name sns.amazonaws.com and its regional variants).
			if ($name === 'amazonaws.com') {
				continue;
			}
			if (substr($name, -14) === '.amazonaws.com') {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $rawBody
	 * @param string $signatureHeader
	 * @param resource|\OpenSSLCertificate $certificate
	 *
	 * @throws SignatureException
	 */
	private function assertSignatureIsValid($rawBody, $signatureHeader, $certificate)
	{
		if (!is_string($signatureHeader) || trim($signatureHeader) === '') {
			throw new SignatureException('Missing Signature header');
		}

		$signature = base64_decode($signatureHeader, true);
		if ($signature === false || $signature === '') {
			throw new SignatureException('Signature header is not valid base64');
		}

		if (!function_exists('openssl_verify')) {
			throw new SignatureException('The openssl extension is required to verify Alexa requests');
		}

		$publicKey = @openssl_pkey_get_public($certificate);
		if ($publicKey === false) {
			throw new SignatureException('Could not extract the public key from the certificate');
		}

		$result = @openssl_verify($rawBody, $signature, $publicKey, OPENSSL_ALGO_SHA1);

		while (openssl_error_string() !== false) {
			// drain the openssl error queue so the next check starts clean
		}

		if ($result === 1) {
			return;
		}
		if ($result === 0) {
			throw new SignatureException('Request signature does not match the request body');
		}
		throw new SignatureException('Request signature could not be verified');
	}

	// ---------------------------------------------------------------- helpers

	/**
	 * @return string[] base64 certificate bodies
	 */
	private static function splitCertificates($pem)
	{
		if (!preg_match_all('/-----BEGIN CERTIFICATE-----(.+?)-----END CERTIFICATE-----/s', (string)$pem, $matches)) {
			return array();
		}
		return $matches[1];
	}

	private static function toPem($body)
	{
		return "-----BEGIN CERTIFICATE-----\n" . trim($body) . "\n-----END CERTIFICATE-----\n";
	}

	/**
	 * @return string|null
	 */
	private function readCache($url)
	{
		if ($this->cacheTtl <= 0 || $this->cacheDir === null) {
			return null;
		}
		$file = $this->cacheFile($url);
		if (!is_file($file)) {
			return null;
		}
		$age = time() - (int)@filemtime($file);
		if ($age < 0 || $age > $this->cacheTtl) {
			return null;
		}
		$pem = @file_get_contents($file);
		return ($pem === false || strpos(ltrim($pem), '-----BEGIN CERTIFICATE-----') !== 0) ? null : $pem;
	}

	private function writeCache($url, $pem)
	{
		if ($this->cacheTtl <= 0 || $this->cacheDir === null) {
			return;
		}
		if (!is_dir($this->cacheDir) && !@mkdir($this->cacheDir, 0700, true) && !is_dir($this->cacheDir)) {
			return; // caching is an optimisation, never fatal
		}
		$file = $this->cacheFile($url);
		$tmp = @tempnam($this->cacheDir, 'alexa');
		if ($tmp === false) {
			return;
		}
		if (@file_put_contents($tmp, $pem) === false) {
			@unlink($tmp);
			return;
		}
		if (!@rename($tmp, $file)) {
			@unlink($tmp);
		}
	}

	private function cacheFile($url)
	{
		return rtrim($this->cacheDir, '/\\') . DIRECTORY_SEPARATOR . 'chain-' . sha1($url) . '.pem';
	}
}
