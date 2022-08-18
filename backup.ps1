& "C:\Program Files (x86)\WinSCP\WinSCP.com" `
  /command `
    "open ftp://jmeno:heslo@ftp2.gransy.com/data/" `
    "synchronize local C:\Users\hellebrand\OneDrive\premiermusic\data\midi\ midi" `
    "synchronize local C:\Users\hellebrand\OneDrive\premiermusic\data\flasinet\ flasinet" `
    "synchronize local C:\Users\hellebrand\OneDrive\premiermusic\data\halfplayback\ halfplayback" `
    "exit"

$winscpResult = $LastExitCode
if ($winscpResult -eq 0)
{
  Write-Host "Success"
}
else
{
  Write-Host "Error"
}

exit $winscpResult
