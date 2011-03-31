# Yui Compressor web-app #

## Description ##

The Yui Compressor web-app is a web based tool for compressing and concatenating JavaScript or CSS files. It is based on the Java YUI Compressor command-line utility written by Julien Lecomte for Yahoo! Inc. See the [Yui Compressor documentation](http://yuilibrary.com/projects/yuicompressor/) for more information.



## History ##

The idea for the Yui Compressor web-app started life as a personal project of [John Hunter](http://johnhunter.info/) and Andrew Smith while working at [Syzygy UK](http://www.syzygy.co.uk/) - a London web agency. 

As part of research on build processes we realised that not all projects will have a formal build system and that the Yui-compressor command-line tool would gain greater acceptance if it could concatenate files and had a simpler interface.

An early version of the web-app was launched and hosted internally with Syzygy. Subsequently all maintenance and development was taken on by John Hunter with released versions being made available to Sygygy developers for internal use.

During this time the web-app went through a number of iterations resulting in a complete rewrite of all the code. We agreed to open-source it in Jan 2011.



## Design goals ##

The project aims to improve the quality of interface developers code by providing:

1.	An efficient and easy to use tool to compress files.
2.	A tool that will encourage develops to write better code.

Underpinning the tool is a belief that the people who (should) care most about the quality of product is the people who create it. Automated build processes can isolate interface developers from quality issues and prevent them from fixing (and learning from) errors in a timely way. With the Yui Compressor web-app we want to encourage personal responsibility for quality.



## Installation ##

Requires: PHP 5.2 and Java 1.4 on the server.

File permissions need to be set to allow the script to write to `tmp/` and `download/` directories.

**Warning:** The web-app does some heavy processing, and allows users to upload files which (after processing) are accessible in the docroot. You should **think very carefully** about resource and security implications before hosting this in an environment where you do not have complete control over access and use.

You install this software at your own risk!



## Usage ##

The compressor can be used to compress individual files or compress several files into a single file in a specified order. It will compress either JavaScript files or CSS files (but not the both in the same operation).

**Note:** The web-app is dependent on HTML5 file api functionality. You will get a warning if your browser does not support this feature.


1.	Select all the files for upload in the single file selection dialog box.

2.	Drag to reorder the files in the web-app, and remove any not required. This allows you to set up the correct files and their loading order easily.

3.	Options are provided to change various compressor parameters - these are hidden and rarely needed as the defaults are good for 99% of the time.

4.	The default output file name can be changed but the web-app is pretty clever at figuring out an appropriate default.

5.	Hitting the compress button sets the compressor running - it can take a while so there is an indicator in the button.

6.	When completed the results are displayed in an overlay. This means that if you need to make a change to a file and re-upload you can just close the overlay, edit the file and compress again without loosing the list of upload files in the app screen. That's a real productivity gain.

7.	The results include a summary of how well each file compressed and a list of warnings from the compressor. These indicate where bad coding practises exist or problems that reduce the effectiveness of the compression (a common one is multiple var declarations in the same scope). Because we still have the uploaded file list its easy to make a code change and hit the compress button again.

8.	The app is built for mulituser. Each compressed file is sandboxed in its own directory. Every time the compressor runs it does a cleanup and removes compressed files older than 1 hour so they don't clog the filesystem.


### Suggested file organisation ###

We recommend keeping all libraries, plugins and stable modules in a lib directory. These are compressed to a single file which will be modified infrequently (named lib-min.js). The main script file where site specific settings and functionality can be optionally compressed for production:

	js/
		main.js      (main script file)
		main-min.js  (optional compressed version of main)
		lib-min.js   (compressed contents of the lib directory)
		lib/
			jquery-1.4.4.js
			jquery.someplugin.js
			my-module.js
			...
			

The output filename defaults are optimised for this approach.



## Options ##

The compressor provides options for both the web-app and the compressor itself. You will rarely need to change from the default settings so these are not initially displayed in the web-app screen.



### Header comment ###

The header comment appears at the top of the compressed file. By default it contains a list of the files compressed and a creation timestamp. This is useful for identifying a particular compressed version of files. You can edit this as required.



### General options ###

These apply whether you are compressing JavaScript or CSS files


#### display informational messages and warnings ####

This allows you to turn off the reporting produced by the compressor. The reports will help you write better code so this is best left checked.


#### Insert line breaks ####

You can insert line breaks in the output file if required. This isn't usually necessary and will increase the size of the file a little. You may want to have a line break after each CSS rule to aid debugging. Also, certain version-control systems have issues with very long lines.

#### End of line style ####

By default the end of line (EOL) character is LF but you may change this if you have problems with mixed EOL markers in version-control systems.

**Note about version control:** Where you encounter issues with committing the compressed file to version-control systems you might consider flagging the file as binary. After-all, there would be little point in attempting to merge changes in a compressed file. 

### JavaScript options ###

These apply to javascript files only.


#### don't compress files ending 'min.js' ####

allows already compressed files to skip the compression and be concatenated into the output file.


#### minify only - don't obfuscate local variables ####

By default the compressor substitutes variable names for shorter (1 or 2 character) identifiers. Normally this is not a problem if you write to good coding standards. But if you reference variables by creating their name at run time (e.g. using eval) then you might need to switch this off. It will result in a much lower level of compression.

See also *Hinting* below for targeting specific variables.


#### preserve semicolons ####

E.G. (such as right before a `}`) This option is useful when compressed code has to be run through JSLint after compression.


#### disable micro-optimizations (e.g. pre-processing string concatenations) ####

This option switches off some of the optimisations. For example it you create a long string with concatenation (for read-ablity) the compressor will convert it to a single string:

	var out = 'Praesent id metus massa, ' +
		'ut blandit odio. Proin quis tortor' +
		' orci. Etiam at risus et justo ' +
		'dignissim congue. Donec congue lacinia.';

becomes

	var out = 'Praesent id metus massa, ... Donec congue lacinia.';



### Hinting ###

It is possible to prevent a local variable, nested function or function
argument from being obfuscated by using "hints". A hint is a string that
is located at the very beginning of a function body like so:

	function fn (arg1, arg2, arg3) {
		"arg2:nomunge, localVar:nomunge, nestedFn:nomunge";

		...
		var localVar;
		...

		function nestedFn () {
			....
		}

		...
	}

The hint itself disappears from the compressed file.



### C-style comments ###

C-style comments starting with `/*!` are preserved. This is useful with
comments containing copyright/license information. For example:

	/*!
	 * TERMS OF USE - EASING EQUATIONS
	 * Open source under the BSD License.
	 * Copyright 2001 Robert Penner All rights reserved.
	 */

becomes:

	/*
	 * TERMS OF USE - EASING EQUATIONS
	 * Open source under the BSD License.
	 * Copyright 2001 Robert Penner All rights reserved.
	 */



## Licence ##

Licenced under the [Simplified BSD License](https://github.com/johnhunter/yui-compressor-webapp/LICENSE.txt):

Copyright 2011 John Hunter. All rights reserved.

