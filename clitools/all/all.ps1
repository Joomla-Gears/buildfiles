# Akeeba Build Files
#
# @package    buildfiles
# @copyright  (c) 2010-2017 Akeeba Ltd
#
Param(
	[string]$operation
)

function showUsage()
{
	$mySelf = Split-Path $MyInvocation.MyCommand.Name -Leaf
	Write-Host "Usage: $mySelf <command>" -Foreground DarkYellow
	Write-Host ""
	Write-Host All repositories -Foreground Blue
	Write-Host pull	  Pull from Git
	Write-Host push	  Push to Git
	Write-Host status  Report repositories with uncommitted changes
	Write-Host Using Akeeba Build Files -Foreground Blue
	Write-Host link    Internal relink
}

if (!$operation)
{
	showUsage
	exit 255
}

Write-Host "All - Loop all repositories" -Foreground White
Write-Host ""

Get-ChildItem -Directory | ForEach-Object {
	$d = $_.Name
	Push-Location $d

	$hasDotGit = Test-Path .git
	
	if ($hasDotGit -eq $False)
	{
		Pop-Location
		
		Continue
	}
	
	$thisRepoMetrics = git remote -v | Select-String -Pattern "git@github.com" | Measure-Object -Line
	
	if ($thisRepoMetrics.Lines -lt 1)
	{
		Pop-Location
		
		Continue
	}
	
	Switch ($operation)
	{
		"pull" {
			Write-Host "Pulling " -Foreground Blue -NoNewline
			Write-Host $d -Foreground Cyan
			git pull --all
		}

		"push" {
			Write-Host "Pushing " -Foreground Green -NoNewline
			Write-Host $d -Foreground Cyan
			git push --all
		}
		
		"status" {
			if ( (git status --porcelain | Measure-Object -Line).Lines -gt 0)
			{
				Write-Host "Dirty " -Foreground Red -NoNewline
				Write-Host $d -Foreground Cyan				
			}
		}
		
		"link" {
			if (Test-Path build)
			{
				Write-Host "Linking " -Foreground Red -NoNewline
				Write-Host $d -Foreground Cyan								
				
				cd build
				phing link
			}
		}
		
		"default" {
			Write-Host "Unknown command $operation" -Foreground Magenta
			
			Pop-Location
			
			Exit 1
		}
	}
	
	Pop-Location
}