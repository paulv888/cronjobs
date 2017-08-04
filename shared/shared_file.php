<?php
if (!function_exists("mb_basename"))
{
  function mb_basename($path)
  {
    $separator = " qq ";
    $path = preg_replace("/[^ ]/u", $separator."\$0".$separator, $path);
    $base = basename($path);
    $base = str_replace($separator, "", $base);
    return $base;
  }
}

if (!function_exists("mb_pathinfo"))
{
  function mb_pathinfo($path, $opt = "")
  {
    $separator = " qq ";
    $path = preg_replace("/[^ ]/u", $separator."\$0".$separator, $path);
    if ($opt == "") $pathinfo = pathinfo($path);
    else $pathinfo = pathinfo($path, $opt);

    if (is_array($pathinfo))
    {
      $pathinfo2 = $pathinfo;
      foreach($pathinfo2 as $key => $val)
      {
        $pathinfo[$key] = str_replace($separator, "", $val);
      }
    }
    else if (is_string($pathinfo)) $pathinfo = str_replace($separator, "", $pathinfo);
    return $pathinfo;
  }
}
?>
