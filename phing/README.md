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

## Building installation packages for FOF 3 powered components

The Common Phing Script is designed to easily build installation packages for FOF 3 powered components without much
fussing around.

Before you begin, you need to have the following folder structure in your repository:

```
Repository Root
   +--- build								Phing build files
   |       +--- templates 					Template XML manifest and version.php files
   +--- component							Component files
   |       +--- backend						Back-end files
   |       +--- frontend					Front-end files
   |       +--- media						Anything that goes in the media/com_something folder of the site
   |       +--- language					Default language files to be installed (recommended: just en-GB files) 
   |       +--- cli							CLI scripts
   |       +--- script.something.php		Joomla! installation script for the PACKAGE
   |       +--- script.com_something.php	Joomla! installation script for the COMPONENT
   +--- modules								Your modules (must be present)
   |       +--- site						Front-end modules (must be present, even if it has no contents)
   |       |      +-- whatever				Files for front-end module mod_whatever...
   |       |      …
   |       +--- admin						Backup-end modules (must be present, even if it has no contents)
   |       |      +-- whatever				Files for back-end module mod_whatever...
   |       |      …
   +--- plugins								Your plugins (must be present, even if it has no contents)
   |       +--- system						System plugins. Likewise for other plugin types.
   |       |      +-- whatever				Files for plg_system_whatever...
   |       …      …
   +--- translations						Language files. The logic of the structure is evident.
   		   +--- _pages
   		   |      +-- index.html			Template index file for the CDN, used to list language packages.
           +--- component					Component translations
           |      +-- backend					Back-end translations
           |      |     +-- en-GB					English (Great Britain)
           |      |     …
           |      +-- frontend
           |      |     +-- en-GB
           |      |     …
           +--- modules
           |      +-- site
           |      |     +-- whatever
           |      |     |      +-- en-GB
           |      |     …
           |      +-- admin
           |      |     +-- whatever
           |      |     |      +-- en-GB
           |      |     …
           +--- plugins
                  +-- system
                  |     +-- whatever
                  |     |      +-- en-GB
                  |     …
                  …
```

Please replace `something` and `com_something` with the name of your extension. For example, if your extension is
`com_magicbus` the script files are called `script.magicbus.php` and `script.com_magicbus.php`.

### If you do NOT have Core and Pro versions (just one edition)

You need to define the following `<fileset>` IDs:

* **component** The files to include in your component package, relative the the `component` directory
* **cli** The files to include in the CLI file package, relative the the `component/cli` directory
* **package** The files to include in the installation package, relative the the `release` directory

A note about the `package` ID. The build script will create ZIP files following the convention com_something.zip,
file_something.zip, pkg_system_whatever.zip, mod_site_whatever.zip, mod_admin_whatever.zip. These are the files you need
to reference in your `<fileset>`.

You will need the following XML files in your `build/templates` directory:

* **something.xml** The Joomla! XML manifest for the component installation ZIP file
* **file_something.xml** The Joomla! XML manifest for the CLI scripts' file extension type installation ZIP file
* **pkg_something.xml** The Joomla! XML manifest for the package extension type installation ZIP file

Where `something` is the name of your extension.

Finally, if you want to include Joomla! installation script you need to use the following naming convention:

* **script.something.php** Joomla! installation script for the Package extension type
* **script.com_something.php** Joomla! installation script for the Component extension type

Where `something` is the name of your extension.

### If you DO have Core and Pro versions (free and paid editions)

You need to define the following `<fileset>` IDs:

* **component-core** The files to include in your component package, relative the the `component` directory
* **cli-core** The files to include in the CLI file package, relative the the `component/cli` directory
* **package-core** The files to include in the installation package, relative the the `release` directory

Use the suffix `-core` for the Core (free) version, `-pro` for the Professional (paid) version.

A note about the `package` ID. The build script will create ZIP files following the convention com_something-core.zip,
file_something-core.zip, pkg_system_whatever.zip, mod_site_whatever.zip, mod_admin_whatever.zip. These are the files you need
to reference in your `<fileset>`.

IMPORTANT: Even though the com_ and file_ ZIP files have a core/pro suffix the actual Joomla! extension name is
com_something and file_something for BOTH the Core and Pro packages. This allows you to upgrade from Core to Pro without
 messing up the `#__extensions` table's records.

You will need the following XML files in your `build/templates` directory:

* **something_pro.xml** The Joomla! XML manifest for the component installation ZIP file
* **file_something_pro.xml** The Joomla! XML manifest for the CLI scripts' file extension type installation ZIP file
* **pkg_something_pro.xml** The Joomla! XML manifest for the package extension type installation ZIP file

Where `something` is the name of your extension. Use the suffix `_core` for the Core (free) version, `_pro` for the
Professional (paid) version.

IMPORTANT: Unlike the refid's the XMl files use an underscore, not a dash, to separate the filename from the core/pro
suffix!

Finally, if you want to include Joomla! installation script you need to use the following naming convention:

* **script.something.php** Joomla! installation script for the Package extension type
* **script.com_something.php** Joomla! installation script for the Component extension type

Where `something` is the name of your extension. Please note that BOTH Core and Pro versions use the SAME installation
scripts. Also note that even though the generated packages are in the format pkg_something-1.2.3-core.zip and
pkg_something-1.2.3-pro.zip BOTH packages have the internal Joomla! extension name pkg_something. This allows you to
upgrade from Core to Pro version without messing up the `#__extensions` table's records.

### Including other extensions in your package

If you want to include extensions other than the component, modules and plugins (and FOF 3) built by the
Common Phing Script you will need to override the `component-packages` target. *Do not override any other target
involved in package building*. The dependencies must be added BEFORE the `package-pkg` dependency. For example:

`<target name="component-packages" depends="my-stuff,some-other-stuff,package-pkg" />`

The targets `my-stuff` and `some-other-stuff` are supposed to include the additional extensions' installation packages
in the `release` folder of the repository. Also remember to change the `package`, `package-core` or `package-pro`
`<fileset>`s to include these additional files into your package.

If you need to clean up those files after build you can do something like:
```
<target name="component-packages" depends="my-stuff,some-other-stuff,package-pkg">
	<delete>
		<fileset dir="${dirs.release}">
			<include name="pkg_other.zip" />
			<include name="tpl_whatever.zip" />
		</fileset>
	</delete>
</target>
```

The `<delete>` task will be called by Phing after the `depends` targets have completed, therefore right after the
package building is complete. Just make sure you don't remove your freshly built pkg_* files!
