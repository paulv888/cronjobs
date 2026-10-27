<?php
// ---------------------------------------------------------------------------
// User management: suspend, restore, remove.
//
// There are only two states, because a third one was more machinery than this
// needs: `ha_mi_remote_users.inuse` is 1 (may sign in) or 0 (may not). Anyone who
// is blocked but still has a row is told their account is *suspended* and to
// contact the administrator, whether the intent was a pause or a permanent stop.
//
// Removing someone entirely is a separate operation: it deletes the row, and then
// there is no account to sign in to at all.
//
// A pending self-registration is `inuse = 0` with `method = 'PENDING'`, which is
// distinguishable and gets its own message ("waiting for approval").
//
// Every state change here also ends the person's live sessions. Changing the row
// without killing the cookie would leave them signed in for up to 30 days.
// ---------------------------------------------------------------------------

if (!defined('HA_USER_BLOCKED'))  define('HA_USER_BLOCKED', 0);
if (!defined('HA_USER_ACTIVE'))   define('HA_USER_ACTIVE', 1);

if (!defined('HA_USER_SUSPENDED_MESSAGE')) {
	define('HA_USER_SUSPENDED_MESSAGE',
		'This account is temporarily suspended. Please contact the administrator.');
}

function haUserFind($userId) {
	if (!is_numeric($userId)) return false;
	$row = PDOFetchRow('SELECT id, username, email, inuse, method, sessions_cutoff
	                      FROM ha_mi_remote_users WHERE id = ?', array((int)$userId));
	return empty($row) ? false : $row;
}

function haUserSetActive($userId, $active) {
	$row = haUserFind($userId);
	if ($row === false) return false;

	PDOExec('UPDATE ha_mi_remote_users SET inuse = ?, sessions_cutoff = ? WHERE id = ?',
		array($active ? HA_USER_ACTIVE : HA_USER_BLOCKED, $active ? null : haSqlDate(time()), (int)$userId));

	// Hard stop. `sessions_cutoff` also prevents the browser quietly minting a new
	// session from its stored Firebase login after the cookie has been revoked.
	haSessionRevokeUser((int)$userId);

	error_log('commands_users: ' . $row['username'] . ' (' . (int)$userId . ') -> '
		. ($active ? 'active' : 'suspended'));
	return true;
}

// Block the account and end its sessions. Reversible with haUserRestore().
function haUserSuspend($userId) {
	return haUserSetActive($userId, false);
}

// Back to normal. They sign in again, because the sessions were killed.
function haUserRestore($userId) {
	$row = haUserFind($userId);
	if ($row === false) return false;
	PDOExec('UPDATE ha_mi_remote_users SET inuse = ?, sessions_cutoff = NULL WHERE id = ?',
		array(HA_USER_ACTIVE, (int)$userId));
	error_log('commands_users: ' . $row['username'] . ' (' . (int)$userId . ') restored');
	return true;
}

// Irreversible: drops the access row, its sessions and its push tokens.
//
// The Firebase account is NOT touched — this code cannot delete it. That matters:
// if the Firebase user still exists, its next sign-in looks like an unknown
// verified email and creates a fresh PENDING request, so they reappear in the
// approval queue. Delete the Firebase account as well when removing someone.
function haUserRemove($userId) {
	$row = haUserFind($userId);
	if ($row === false) return false;

	PDOExec('DELETE FROM ha_sessions WHERE user_id = ?', array((int)$userId));
	PDOExec('DELETE FROM fcm_tokens WHERE user_id = ?', array((int)$userId));
	$removed = PDOExec('DELETE FROM ha_mi_remote_users WHERE id = ?', array((int)$userId));

	error_log('commands_users: ' . $row['username'] . ' (' . (int)$userId
		. ') removed entirely - delete the Firebase account too');
	return $removed > 0;
}

// What a management screen needs, without handing out the whole table.
function haUserList() {
	return PDOFetchRows('SELECT id, username, email, inuse, method, sessions_cutoff
	                       FROM ha_mi_remote_users ORDER BY id');
}

// 'pending_approval', 'user_suspended', or null when the account may sign in.
function haUserBlockReason($row) {
	if (empty($row)) return null;
	if ((int)$row['inuse'] === HA_USER_ACTIVE) return null;
	return strtoupper((string)$row['method']) === 'PENDING' ? 'pending_approval' : 'user_suspended';
}

// The sentence to show for that reason.
function haUserBlockMessage($row) {
	$reason = haUserBlockReason($row);
	if ($reason === null) return '';
	if ($reason === 'pending_approval') return 'Your account is waiting for approval.';
	return HA_USER_SUSPENDED_MESSAGE;
}
?>
