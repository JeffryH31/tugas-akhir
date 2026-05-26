$files = Get-ChildItem "c:\Project Web\tugas-akhir\resources\js" -Recurse -Filter "*.vue"
foreach ($f in $files) {
    $c = [System.IO.File]::ReadAllText($f.FullName)
    $rel = $f.FullName.Replace("c:\Project Web\tugas-akhir\resources\js\", "")
    
    # Check for setInterval without onUnmounted cleanup
    $hasSetInterval = $c.Contains("setInterval")
    $hasOnUnmounted = $c.Contains("onUnmounted")
    if ($hasSetInterval -and -not $hasOnUnmounted) {
        Write-Output "SETINTERVAL_NO_CLEANUP: $rel"
    }
    
    # Check for addEventListener without removeEventListener
    $hasAddEvent = $c.Contains("addEventListener")
    $hasRemoveEvent = $c.Contains("removeEventListener")
    if ($hasAddEvent -and -not $hasRemoveEvent) {
        Write-Output "ADDEVENT_NO_CLEANUP: $rel"
    }
}