$ErrorActionPreference = 'Stop'

$stagingUrl = 'https://staging.cywater.org'
$testUser = 'cywater_http_verify_' + (Get-Date -Format 'yyyyMMddHHmmss')
$testPassword = [guid]::NewGuid().ToString('N') + 'Aa1x'
$cookieFile = Join-Path $env:TEMP ('cywater-cookie-' + [guid]::NewGuid().ToString('N') + '.txt')
$formFile = Join-Path $env:TEMP ('cywater-form-' + [guid]::NewGuid().ToString('N') + '.html')
$sshKey = Join-Path $env:USERPROFILE '.ssh\cywater-hostinger-ed25519'
$remote = 'u111638297@45.130.228.213'
$remoteRoot = '/home/u111638297/domains/cywater.org/public_html/staging'
$registrationGate = $false
$checkoutGate = $false

try {
	curl.exe -sS -c $cookieFile "$stagingUrl/member-register/?redirect_to=https%3A%2F%2Fstaging.cywater.org%2Fmembership-checkout%2F" -o $formFile
	$form = Get-Content -Raw -LiteralPath $formFile
	$nonce = [regex]::Match($form, 'name="cywater_register_nonce" value="([^"]+)"').Groups[1].Value
	if (-not $nonce) {
		throw 'Registration nonce not found.'
	}

	$registrationResult = curl.exe -sS -b $cookieFile -c $cookieFile -o NUL -w '%{http_code}|%{redirect_url}' `
		--data-urlencode "cywater_register_nonce=$nonce" `
		--data-urlencode 'cywater_register_action=1' `
		--data-urlencode 'redirect_to=https://staging.cywater.org/membership-checkout/' `
		--data-urlencode 'company=' `
		--data-urlencode "username=$testUser" `
		--data-urlencode 'email=contact@cywater.org' `
		--data-urlencode "password=$testPassword" `
		--data-urlencode "password_confirm=$testPassword" `
		"$stagingUrl/member-register/"

	$checkoutResult = curl.exe -sS -b $cookieFile -o NUL -w '%{http_code}|%{redirect_url}' "$stagingUrl/membership-checkout/"
	$registrationGate = $registrationResult -like '302|*verify-email*'
	$checkoutGate = $checkoutResult -like '302|*verify-email*'
} finally {
	ssh -i $sshKey -p 65002 -o BatchMode=yes $remote "cd $remoteRoot && wp user delete '$testUser' --yes >/dev/null 2>&1"
	Remove-Item -LiteralPath $cookieFile, $formFile -Force -ErrorAction SilentlyContinue
}

Write-Output "RegistrationGate=$registrationGate CheckoutGate=$checkoutGate Cleanup=True"
if (-not ( $registrationGate -and $checkoutGate )) {
	exit 1
}
