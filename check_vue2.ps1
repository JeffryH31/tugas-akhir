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
    
    # Check for local formatDate/formatDateTime/formatDuration/normalizeHexColor duplicates
    if ($c -match "const formatDate\s*=") {
        Write-Output "DUPLICATE_UTIL_formatDate: $rel"
    }
    if ($c -match "const formatDateTime\s*=") {
        Write-Output "DUPLICATE_UTIL_formatDateTime: $rel"
    }
    if ($c -match "const formatDuration\s*=") {
        Write-Output "DUPLICATE_UTIL_formatDuration: $rel"
    }
    if ($c -match "const normalizeHexColor\s*=") {
        Write-Output "DUPLICATE_UTIL_normalizeHexColor: $rel"
    }
    if ($c -match "const normalizeLabelColor\s*=") {
        Write-Output "DUPLICATE_UTIL_normalizeLabelColor: $rel"
    }
    
    # Check for native confirm() (not window.confirm, just bare confirm)
    $lines = $c -split "`n"
    for ($i = 0; $i -lt $lines.Count; $i++) {
        $line = $lines[$i]
        if ($line -match '\bconfirm\s*\(' -and $line -notmatch 'confirmDialog\|useConfirmDialog\|confirmPassword\|confirmsTwoFactor\|confirmDeleteList\|confirmDeleteFolder\|confirmDeleteSpace\|confirmationName\|confirmingPassword\|await confirm') {
            Write-Output "NATIVE_CONFIRM: ${rel}:$($i+1): $($line.Trim())"
        }
    }
}