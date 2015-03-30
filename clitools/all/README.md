# The `all` tool

This script can be used under any bash shell such as the one commonly used in Linux and Mac OS X, or the bash shell provided in Windows by Git Bash, Cygwin etc. It is meant to iterate through each of the subdirectories, figure out if it contains a Git repository and then pull or push it.

Usage: ./all pull|push

Pulling all repositories:

`$ ./all pull`

This runs a `git pull --all` on all first level subdirectories containing a Git working copy

Pushing all repositories:

`$ ./all push`

This runs a `git push; git push --tags` on all first level subdirectories containing a Git working copy