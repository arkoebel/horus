@ECHO OFF
setlocal DISABLEDELAYEDEXPANSION
SET BIN_TARGET=%~dp0/../stevegrunwell/runkit7-installer/bin/install-runkit.sh
sh "%BIN_TARGET%" %*
