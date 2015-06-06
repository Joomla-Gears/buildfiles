# Common Phing Script

You can use this common Phing script to automate the build and release of your Joomla! extensions. This script is
designed to be used with the [Phing](http://phing.info) build automation tool. The tasks in this file are designed for
publishing software on Joomla! sites which use [Akeeba Release System](http://github.com/akeeba/release-system).

Language file builds are designed to be uploaded to Amazon S3 and made available through an Amazon CloudFront CDN. If
you are not using Amazon AWS you can skip this feature.

Furthermore, if you are publishing software which uses FOF 3 you *MUST* use the tasks in this repository to make sure
that you are only including the latest official build of FOF 3 with your software. Do NOT include a dev release of
FOF 3 with your packages. It's no longer allowed; it creates havoc! Thank you.

## Setup

### Pre-requisites

Before you begin you need the following directory layout:

* `buildfiles` A working copy of this repository
* `releasemaker` A working copy of [Akeeba Release Maker](https://github.com/akeeba/releasemaker). Optional, only used
   by the `release` task.
* Your working copy is in a subdirectory at the same level as the aforementioned directories.
* Your Phing script is located in the `build` directory of your working copy.

Keep in mind that the `ftpdeploy` task assumes that you're using an SFTP-capable server. If this is not the case with
your server please be a good sport and DO NOT RELEASE SOFTWARE TO THE PUBLIC. Plain FTP (and to a certain extent FTPS)
is a vulnerable protocol. It is trivial for an attacker to steal your credentials and compromise your site or the
software contained therein.

### Using with your Phing script

Include it in your own Phing script
with:

`<import file="${phing.dir}/../../buildfiles/phing/common.xml" />`
  
### Build properties

The common Phing script relies on build properties to customise it. A list of all available options and their
explanations can be found in the `default.properties` file shipped in this directory.

You are supposed to put a `build.properties` file next to your own Phing script, i.e. inside your repository's `build`
directory. Since you will commit this file to Git DO NOT include privileged information such as passwords.
Privileges information is supposed to be placed in another `build.properties` file in the directory above your
repository's root. If you want a temporary override for testing/experimentation put it in an `override.properties`
file next to your own Phing script but do NOT commit this file to Git.

The directory structure looks like this:

```
Projects										The root directory of your Git working copies
	+--- buildfiles								This repository
	+--- releasemaker							Akeeba Release Maker
	+--- yourProject							Your project's working copy
	|       +--- build
	|       |      +--- build.xml				Your Phing script
	|       |      +--- build.properties		Unprivileged build properties, committed to Git
	|       |      +--- override.properties		Overrides; do NOT commit to Git. Use only for testing / experimentation.
	|       …      …
	+--- build.properties						Privileged build properties, outside the repository, NOT commit to Git.
```

The privileged properties we recommend putting in the `build.properties` outside your Git repositories' roots are:

* s3.access
* s3.private
* s3.bucket
* s3.path
* release.update_dir
* s3.cdnhostname
* scp.* (all keys starting with `scp.`)
* release.api.endpoint
* release.api.username
* release.api.password

*Pro tip*: these properties are common among all your projects. The more projects you have using the Common Phing Script
the more sense it makes having all this information written down in just one file. When you rotate your passwords
(you do rotate your passwords at least once a month, right? RIGHT?!) you just need to update a single file. Cool! 