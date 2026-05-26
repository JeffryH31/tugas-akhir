$files = Get-ChildItem "c:\Project Web\tugas-akhir\resources\js" -Recurse -Filter "*.vue"
foreach ($f in $files) {
    $c = [System.IO.File]::ReadAllText($f.FullName)
    $rel = $f.FullName.Replace("c:\Project Web\tugas-akhir\resources\js\", "")
    $lines = $c -split "`n"
    for ($i = 0; $i -lt $lines.Count; $i++) {
        $line = $lines[$i]

        # v-if and v-for on same element
        if ($line.Contains("v-if") -and $line.Contains("v-for")) {
            Write-Output "V-IF+V-FOR: ${rel}:$($i+1)"
        }

        # let used for reactive refs (should be const)
        if ($line -match 'let\s+\w+\s*=\s*(ref|computed|reactive)\s*\(') {
            Write-Output "LET_REF: ${rel}:$($i+1): $($line.Trim())"
        }

        # console.log left in code
        if ($line.Contains("console.log")) {
            Write-Output "CONSOLE_LOG: ${rel}:$($i+1): $($line.Trim())"
        }

        # window globals (should use composables)
        if ($line.Contains("window.showSnackbar") -or $line.Contains("window.openCreateSpaceDialog") -or $line.Contains("window.location.reload")) {
            Write-Output "WINDOW_GLOBAL: ${rel}:$($i+1): $($line.Trim())"
        }

        # window.location.href for navigation (use <a> or router instead)
        if ($line.Contains("window.location.href")) {
            Write-Output "WINDOW_HREF: ${rel}:$($i+1): $($line.Trim())"
        }

        # native browser confirm() (use useConfirmDialog instead)
        if ($line -match '\bconfirm\s*\(' -and $line -notmatch 'useConfirmDialog|confirmDialog|confirmsTwoFactor') {
            Write-Output "NATIVE_CONFIRM: ${rel}:$($i+1): $($line.Trim())"
        }

        # JSON deep clone (use structuredClone instead)
        if ($line.Contains("JSON.parse") -and $line.Contains("JSON.stringify")) {
            Write-Output "JSON_CLONE: ${rel}:$($i+1): $($line.Trim())"
        }

        # :key="index" in v-for (use stable id instead)
        if ($line -match ':key="index"') {
            Write-Output "KEY_INDEX: ${rel}:$($i+1): $($line.Trim())"
        }

        # Local duplicate utility functions (should import from utils/)
        if ($line -match '^const (normalizeHexColor|formatDuration|formatDate|formatDateTime)\s*=') {
            Write-Output "LOCAL_UTIL: ${rel}:$($i+1): $($line.Trim())"
        }

        # Shorthand prop WITHOUT being inside a validator object
        # Only flag `propName: Type,` at top-level of defineProps (not `type: Object,` inside validator)
        # Heuristic: line has exactly `word: Type,` with no `{` before it on same line
        if ($line -match '^\s{4}(\w+):\s*(Object|Array|String|Number|Boolean),?\s*$' -and $line -notmatch '^\s+type:') {
            Write-Output "SHORTHAND_PROP: ${rel}:$($i+1): $($line.Trim())"
        }
    }
}
