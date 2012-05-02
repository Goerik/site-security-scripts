<?php 
/*	lookforbadguys.php		 2012-04-09
	Copyright (C)2012 Karen Chun, Steven Whitney.
	Initially published by http://25yearsofprogramming.com.

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License (GPL)
	Version 3 as published by the Free Software Foundation.
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

--Purpose: iterate through server files looking for hacker code snippets, backdoor scripts, 
  suspicious .htaccess code, suspicious file names. 
  Suspicious things to search for are stored in easily modifiable lists of regular expressions.

--Tested with PHP 5.2 and 5.3. It might work with earlier versions.
--It is designed for use in either Linux or Windows. 
  On my system, it runs much slower in Windows.

--Not all things it finds are hacks. Not all hacks are found. 
--You should also search manually for weird files (such as .php files) in your image directories, 
  especially if your .htaccess has redirects or was made executable. 
--Some searches are commented out because they can give too many false positives.

----------
CHANGELOG:

--2011-03-08 First published

--2011-09-08 Steven Whitney
  1. Rewrote the recursive directory search function FindAndProcessFiles(). 
  2. Added ability to exit with 'Forbidden' message unless request is from a specific IP address.
  3. Changed malicious snippet regexes to allow for any whitespace, not just spaces, between function name and "(".
  4. Renamed $SuspiciousFileAndPathNames array to $SuspiciousFileNames because path names are not tested.
  5. Revised comments.
  6. Published under GPL3 license. 

--2011-09-10 Steven Whitney
  1. Added global variables to store counts of files/directories processed, and functions to reset/report them.
     Added a global array variable to store the list of files that matched the regex(es).
  2. Broke apart FindAndProcessFiles() into two functions:
     
     1) BuildFileList() only traverses the filesystem and builds the list of files matching the regex.
     This makes it a general purpose file-find search function like the Linux "find" utility program.
     The regex for selecting filenames can either be a single regex string or an array of them.
     If it is an array of regexes, a file is added to the list if its name matches any of them.
     
     2) The new FindAndProcessFiles() calls BuildFileList() to build the file list, sorts the list,
     and then applies the handler function to each file in the list. 

--2011-09-13 Steven Whitney
  1. Added GetCanonicalPath() and $UseAbsoluteFilePaths to control whether paths are absolute or relative.
  2. Reorganized code blocks so that important user-configuration settings are at the top,
     data arrays and handler functions for each search are grouped together,
     and support functions are all at the end.
  3. Added $FullpathExcludeRegexes and code using it, to allow excluding files from examination.
     FindAndProcessFiles() and BuildFileList() both take 1 more argument.
     A file is added to the file list if its name matches the include regexes, 
     UNLESS its fullpath also matches any of the $FullpathExcludeRegexes.
     dirs are always traversed, but individual files in them can all be excluded from examination.
     This allows dirs to be excluded with or without excluding their subdirs.
     Example exclusion: '#/tiny_mce/.*\.php$#i' 
  4. In the search routines, one set of variables is reused instead of using different variable names,
     to make it clear that there are 3 routines basically doing the same thing.
     Only the data and handler functions change.
  5. maliciouscodesnippets() renamed to FindMaliciousCodeSnippets.
     In snippet search output, each file is only listed once.
     After that, a list of all threats found, with initial portion of the matching strings for visual review.
     Moved the special cases (RewriteRule, AddHandler, <script, <iframe) into normal snippet search array.
	 Moved the lookforbadguys.php exclusion into the fullpath exclusion array.
  6. Changed default script execution time limit from unlimited to 5 minutes.
  7. Changed CleanColorText() to allow numeric colors: #FFFFFF.
  8. Some <script and <iframe detection is now enabled by default, with some of the
     most common safe sources of them as exceptions. 

--2011-09-14 Steven Whitney
  1. In FindMaliciousCodeSnippets(), moved the option to print each filename processed to end of function 
     and changed its method.
  2. In BuildFileList(), moved the test for whether a file matches the fullpath exclusion 
     so that it is only performed if the file has matched the inclusion criteria
	 (this fixed an inefficiency in previous version).

--2011-09-27 Steven Whitney
  1. Revised the base64_decode regex to show more matched text, if present.
  2. Revised the backtick operator regex to be less greedy and show individual occurrences.

--2012-04-09 Steven Whitney
  1. Added regexes to $SuspiciousSnippets array to find preg_replace with /e (eval) modifier.
  2. New user configuration option: date_default_timezone_set().
  3. Output lines showing filenames now also show the file's last-modified timestamp.
  4. New search routine can report all files modified within a specified date/time range.
  5. Output colors are defined in an array to make it easier to change them.
  
----
  
*/ 

# ================================================================================
# USER CONFIGURATION SECTION
# ================================================================================

/*	
The next line only allows the script to run if the request came from your IP address.
It allows you to put the script in a public folder but prevent others from running it.
Change the IP address to yours. (127.0.0.1 is localhost.) 
*/

if($_SERVER['REMOTE_ADDR'] !== '127.0.0.1') exit('Forbidden');


/*
Searches will be done in this directory and all dirs inside it. 
The default of './' means current directory, where this script is now.
Thus, to search everything inside public_html, that's where this file should be put.
To search outside public_html, or to search a folder other than where this script is stored, 
change this to the full pathname, such as /home/userid/ or /home/userid/public_html/somefolder/.
Always use forward slashes for the path. Windows example: C:/wamp/apache2/htdocs/test/
*/

$StartPath = './';


# TRUE  = report shows full file paths such as /home/userid/public_html/blog/...
# FALSE = report shows relative file paths such as ./blog/...

$UseAbsoluteFilePaths = TRUE;


# These set maximum execution time, in seconds. The script can take a while.
# These have no effect if you run PHP in "safe mode" (safe mode is usually undesirable). 
# Set to '0' for unlimited.

ini_set('max_execution_time', '300');
ini_set('set_time_limit', '300');

ini_set('display_errors', '1');		# 1=TRUE, ensure that you see errors such as time-outs.


/*
The timezone must be given a value (any legal value) to avoid a PHP warning 
every time the date() function is called.
Ideally, you should enter in the line below the correct timezone 
that your server uses for its file timestamps.
The PHP manual at http://www.php.net/manual/en/timezones.php 
has list of supported timezone strings.
*/

date_default_timezone_set('America/Los_Angeles');


/*
The program can optionally search for files with suspicious last-modified timestamps.
To use that feature, define here the time window you consider suspicious,
such as the period during which you know files were being modified by a hack.
The search for suspicious timestamps is not performed 
unless you define a more recent time window here than the examples shown.

REQUIRED FORMAT is "YYYY-MM-DD HH:MM:SS" 
*/

$TimeRangeStart = '1980-04-09 05:01:05';
$TimeRangeEnd   = '1980-04-10 23:59:59';


# TEXT COLORS IN AN ARRAY, TO MAKE IT EASIER TO CHANGE THEM.

$Colors = array
(
	'timestamp'  => '#808080',	# FILE TIMESTAMPS
	'filename'   => 'blue',		# FILE PATHS AND NAMES
	'suspicious' => 'red',		# WARNINGS, AND REGULAR EXPRESSIONS
	'status'     => 'green',	# STATUS MESSAGES
	'snippet'    => 'black'		# TEXT OF SUSPICIOUS SNIPPETS
);

# ================================================================================
# GLOBAL VARIABLES
# ================================================================================

# Besides being useful, reporting the counts helps ensure that 
# new recursion methods work the same as the old.

$FilesCount = 0;
$FilesMatchedCount = 0;
$DirectoriesCount = 0;
$DirectoriesMatchedCount = 0;

# This array must be global because the function that builds it is re-entrant.

$AllFilesToProcess = array();	


?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<head> 
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"> 
<meta http-equiv="Content-Language" content="en-us">
<title>Looking for bad guys</title> 
</head> 

<body> 
<p>Looking for bad guys. </p> 
<p>This script looks for traces of malicious code including code injections,
modified .htaccess that makes images executable, and so on.</p> 
<p>

<?php 

$RealPath = GetCanonicalPath($StartPath);
if($RealPath === FALSE)
	exit(CleanColorText("Cannot continue. The starting directory is inaccessible to PHP.", $Colors['suspicious']) . "<br>");
if($UseAbsoluteFilePaths)
	$StartPath = $RealPath;


# ================================================================================
# START OF SEARCH ROUTINES. 
# ================================================================================
/*
This program does two things: 1) finds files, and 2) does something with each one.

When designing a search, the two questions to ask are: 

1) Which types of files (by their names) do I want to find or perform an action on?
2) What action do I want to do on each one?

Each search requires these data items to be defined:

1) An array that is a list of Perl-Compatible Regular Expressions (PCRE) of filenames to match. 
   The program searches directories for all the filenames that match any of the regexes.

2) Another array that is a list of PCREs of fullpaths NOT to match. 
   This allows excluding files in certain directories.
   If a file's NAME matches any regex in list 1) 
   and its PATH+NAME does NOT match any regexes list 2) (the exclusions), 
   its name gets passed to the handler function.

3) The handler function. It can perform any action you want on the file whose name is given to it.
   Some of the handler functions below merely report that the filename is suspicious, but do nothing else.
   Another handler searches the file extensively for malicious snippets and reports each one found.
   You could write a handler that automatically cleans the snippet out of the file, 
   or even deletes the file automatically. The handler can do anything.

*/

# ================================================================================
# 1) SUSPICIOUS FILENAMES.
# Files with these strings in their *names* will be reported as suspicious.
# There is currently no method provided to check for suspiciously named folders.
# ================================================================================
# FILENAMES TO MATCH

$FileMatchRegexes = array
(
#	'/root/i',
#	'/kit/i',
	'/c(99|100)/i',
	'/r57/i',
	'/gifimg/i'
);
# AND FULLPATHS TO EXCLUDE FROM EXAMINATION

$FullpathExcludeRegexes = array
(
	'#lookforbadguys\.php$#i'
);

# --------------------------------------------------------------------------------
# HANDLER FUNCTION - THIS IS THE ACTION PERFORMED ON A FILE WHOSE NAME IS A MATCH.

function badnames($filename) 
{ 
	global $Colors;
	
	echo 
		CleanColorText(date('Y-m-d H:i:s ', filemtime($filename)), $Colors['timestamp']) . 
		CleanColorText($filename, $Colors['filename']) . 
		" is a " . 
		CleanColorText('suspicious file name', $Colors['suspicious']) . ".<br>"; 
}   

# --------------------------------------------------------------------------------
# THIS CODE ACTUALLY DOES THE SEARCH.

echo CleanColorText("Searching for files with suspicious names...", $Colors['status']) . "<br>";

FindAndProcessFiles($StartPath, $FileMatchRegexes, $FullpathExcludeRegexes, 'badnames'); 


# ================================================================================
# 2) WORDPRESS PHARMA HACK SUSPICIOUS FILENAMES.
# Files matching these names will be reported as possible pharma hack files.
# Regexes are based on the naming conventions described at 
# http://www.pearsonified.com/2010/04/wordpress-pharma-hack.php
# ================================================================================
# FILENAMES TO MATCH

$FileMatchRegexes = array
(
	'/^\..*(cache|bak|old)\.php/i',	# HIDDEN FILES WITH PSEUDO-EXTENSIONS IN THE MIDDLE OF THE FILENAME
	'/^db-.*\.php/i',

	# Permit the standard WordPress files that start with class-, but flag all others as suspicious.
	# The (?!) is called a negative lookahead assertion. It means "not followed by..."

	'/^class-(?!snoopy|smtp|feed|pop3|IXR|phpmailer|json|simplepie|phpass|http|oembed|ftp-pure|wp-filesystem-ssh2|wp-filesystem-ftpsockets|ftp|wp-filesystem-ftpext|pclzip|wp-importer|wp-upgrader|wp-filesystem-base|ftp-sockets|wp-filesystem-direct)\.php/i'
);
# AND FULLPATHS TO EXCLUDE FROM EXAMINATION

$FullpathExcludeRegexes = array
(
	'#lookforbadguys\.php$#i'
);

# --------------------------------------------------------------------------------
# HANDLER FUNCTION - THIS IS THE ACTION PERFORMED ON A FILE WHOSE NAME IS A MATCH.
function pharma($filename) 
{ 
	global $Colors;

	echo 
		CleanColorText(date('Y-m-d H:i:s ', filemtime($filename)), $Colors['timestamp']) . 
		CleanColorText($filename, $Colors['filename']) . 
		" is most likely a " . 
		CleanColorText('pharma hack', $Colors['suspicious']) . ".<br>"; 
} 

# --------------------------------------------------------------------------------
# THIS CODE ACTUALLY DOES THE SEARCH.

echo 
	"<br>" . 
	CleanColorText("Searching for files with names related to Wordpress pharma hack...", $Colors['status']) . 
	"<br>";

FindAndProcessFiles($StartPath, $FileMatchRegexes, $FullpathExcludeRegexes, 'pharma'); 


# ================================================================================
# 3) MALICIOUS CODE SNIPPETS.
# Search text files for snippets of malicious code and report all that are found.
# ================================================================================
# FILENAMES TO MATCH
# Ideally, this list should contain all common extensions of text files
# that can become hazardous when malicious text is injected into them.

$FileMatchRegexes = array
(
	'/\.htaccess$/i',
	'/\.php[45]?$/i',
	'/\.html?$/i',
	'/\.aspx?$/i',
	'/\.inc$/i',
	'/\.cfm$/i',
	'/\.js$/i',
	'/\.txt$/i',
	'/\.css$/i'
);
# AND FULLPATHS TO EXCLUDE FROM EXAMINATION

$FullpathExcludeRegexes = array
(
	'#lookforbadguys\.php$#i'
);

# --------------------------------------------------------------------------------
# HANDLER FUNCTION - THIS IS THE ACTION PERFORMED ON A FILE WHOSE NAME IS A MATCH.

function FindMaliciousCodeSnippets($filename) 
{ 
	global $Colors;

	if(!is_readable($filename))
	{
		echo "Warning: Unable to read " . CleanColorText($filename, $Colors['filename']) . 
			". Check it manually and check its access permissions.<br>";
		return;
	}

	# READ THE FILE INTO A STRING, WITH LINE ENDS REMOVED AND WHITESPACE COMPRESSED.
	$file = file_get_contents($filename);  
	$file = preg_replace('/\s+/', ' ', $file);  

	# The file is searched for each of these snippets of suspicious text.
	# These are regular expressions with the required /DELIMITERS/ and with metachars escaped.
	# /i at the end means case insensitive. 
	# PHP function names are case-insensitive.
	# If your regex itself contains / chars, you can use a different 
	# char as a delimiter like this: '#delimited#i' to avoid confusion.
	
	$SuspiciousSnippets = array
	(
		# POTENTIALLY SUSPICIOUS CODE

		'/edoced_46esab/i',
		'/passthru\s*\(/i',
		'/shell_exec\s*\(/i',
		'/document\.write\s*\(unescape\s*\(/i',

		# THESE CAN GIVE MANY FALSE POSITIVES WHEN CHECKING WORDPRESS AND OTHER CMS.
		# NONETHELESS, THEY CAN BE IMPORTANT TO FIND, ESPECIALLY BASE64_DECODE.

		# THIS IS MUCH MORE SUSPICIOUS IF THE MATCHED TEXT CONTAINS THE EVAL() CODE.

		'/(eval\s*\(.{0,40})?base64_decode\s*\(/i',

		'/system\s*\(/i',		

		# --------------------		
		# OCCURRENCES OF POSSIBLE PCRE PATTERNS WITH
		# THE -e (EVAL) PATTERN MODIFIER THAT IS USED BY PREG_REPLACE.

		# (1) VARIABLE DEFINITIONS CONTAINING PCRE PATTERNS USING THE 'e' OPTION,
		# FOLLOWED FAIRLY CLOSELY BY A CALL TO PREG_REPLACE.
		# THE 2 VARIATIONS ALLOW FOR SINGLE AND DOUBLE-QUOTES IN THE VARIABLE DECLARATION.

		'#\$\S+\s*=\s*[\x22]([^A-Za-z0-9[:space:]\x5C\x22])[^\x22]{0,75}\1[imsxADSUXJu]*e.{0,75}[pP][rR][eE][gG]_[rR][eE][pP][lL][aA][cC][eE]\s*\(#',	# CASE-SENSITIVE REQUIRED

		'#\$\S+\s*=\s*[\x27]([^A-Za-z0-9[:space:]\x5C\x27])[^\x27]{0,75}\1[imsxADSUXJu]*e.{0,75}[pP][rR][eE][gG]_[rR][eE][pP][lL][aA][cC][eE]\s*\(#',	# CASE-SENSITIVE REQUIRED

		# (2) PREG_REPLACE CALLS THAT USE THE -e OPTION.
		# THE 2 VARIATIONS ALLOW FOR SINGLE AND DOUBLE-QUOTES AROUND THE PCRE PATTERN.

		'#[pP][rR][eE][gG]_[rR][eE][pP][lL][aA][cC][eE]\s*\(\s*[\x22]([^A-Za-z0-9[:space:]\x5C])[^\x22]{0,75}\1[imsxADSUXJu]*e#',	# CASE-SENSITIVE REQUIRED

		'#[pP][rR][eE][gG]_[rR][eE][pP][lL][aA][cC][eE]\s*\(\s*[\x27]([^A-Za-z0-9[:space:]\x5C])[^\x27]{0,75}\1[imsxADSUXJu]*e#',	# CASE-SENSITIVE REQUIRED
		# --------------------		

		# PHP BACKTICK OPERATOR INVOKES SYSTEM FUNCTIONS, SAME AS system(),
		# BUT THIS TEST CAN PRODUCE MANY FALSE POSITIVES BECAUSE 
		# BACKTICKS ARE ALSO DATABASE,TABLE,FIELD NAME DELIMITERS IN SQL QUERIES.
		# MATCHED TEXT IS SUSPICIOUS IF IT CONTAINS OPERATING SYSTEM COMMANDS.
		# USUALLY NOT SUSPICIOUS IF IT CONTAINS DATABASE TABLE OR FIELD NAMES.

		'/`[^`]+`/',		

		'/phpinfo\s*\(/i',

								# THIS SET GENERATES MANY FALSE POSITIVES
#		'/chmod\s*\(/i',
#		'/mkdir\s*\(/i',
#		'/fopen\s*\(/i',
#		'/fclose\s*\(/i',
#		'/readfile\s*\(/i',

								# THESE WERE PREVIOUSLY SPECIAL CASES; NOW MOVED INTO THIS ARRAY.
		'/RewriteRule\s/i',		# SUSPICIOUS IF THE DESTINATION IS A DIFFERENT SITE OR SUSPICIOUS FILE.
		'/AddHandler\s/i',		# THIS CAN MAKE IMAGE OR OTHER FILES EXECUTABLE.


		# JAVASCRIPT SNIPPETS WHOSE SRC= REFERENCES AN HTTP:// SOURCE OTHER THAN ONES KNOWN TO BE SAFE.
		# EVEN WITH EXCEPTIONS, THIS CAN GIVE MANY FALSE POSITIVES.
		'@<script[^>]+src=[\x22\x27]?http://(?!(www\.(google-analytics|gmodules)\.com|pagead2\.googlesyndication\.com/pagead/|(ws\.|((www|cls)\.assoc-))amazon\.com/))[^>]*>@i',			


		# IFRAMES, WITH A KNOWN-HARMLESS EXCLUSION. 
		# IFRAME SEARCH CAN GIVE MANY FALSE POSITIVES IN SOME WEBSITES.

		'@<iframe[^>]+src=[\x22\x27]?http://(?!(rcm\.amazon\.com/))[^>]*>@i',			


		# SUSPICIOUS NAMES. SOME HACKERS SIGN THEIR SCRIPTS. MANY NAMES COULD BE PUT INTO THIS LIST.
		# HERE IS A GENERIC EXAMPLE OF TEXT FROM A DEFACED WEB PAGE.

		'/hacked by\s/i',

		# OTHER SUSPICIOUS TEXT STRINGS

		'/web[\s-]*shell/i',	# TO FIND BACKDOOR WEB SHELL SCRIPTS.
		'/c(99|100)/i',			# THE NAMES OF SOME POPULAR WEB SHELLS.
		'/r57/i',
		
		# YOU COULD/SHOULD ADD TO THIS LIST SOME REGULAR EXPRESSIONS TO MATCH THE NAMES OF 
		# MALICIOUS DOMAINS AND IP ADDRESSES MENTIONED IN YOUR 
		# GOOGLE SAFE BROWSING DIAGNOSTIC REPORT. 
		# SOME EXAMPLES:

		'/gumblar\.cn/i',
		'/martuz\.cn/i',
		'/beladen\.net/i',
		'/gooqle/i',			# NOTE THIS HAS A Q IN IT.
#		'/127\.0\.0\.1/',		# COMMENTED-OUT EXAMPLE OF AN IP ADDRESS REGEX

		# THESE 2 ARE THE WORDPRESS CODE INJECTION IN FRONT OF EVERY INDEX.PHP AND SOME OTHERS 

		'/_analist/i',			# EACH LIST ENTRY MUST BE TERMINATED WITH A COMMA...
		'/anaiytics/i'			# EXCEPT THE LAST ENTRY MUST NOT HAVE A COMMA.

		
	);

	# ACCUMULATES ALL THE WARNING MESSAGES FOR THIS FILE.
	$OutputText = array
	(
		CleanColorText(date('Y-m-d H:i:s ', filemtime($filename)), $Colors['timestamp']) . 
		CleanColorText($filename, $Colors['filename'])
	);

	# SEARCH THE FILE FOR EACH OF THE ABOVE SNIPPETS.
	foreach($SuspiciousSnippets as $snippet) 
	{
		$matches = array();
		if($matchcount = preg_match_all($snippet, $file, $matches, PREG_PATTERN_ORDER | PREG_OFFSET_CAPTURE))	
		{
			$i = 0;
			foreach($matches[0] as $occurrence)	# $occurrence is an array itself 0=>string, 1=>offset
			{
				$i++;
				# THE 80 CHARACTERS AFTER START OF MATCH INSTANCE
				$s = substr($file, $occurrence[1], 80);	
				$newline = (($i === 1) ? '<br><br>' : '<br>');
				$OutputText[] = $newline . 
								CleanColorText("Regex ($i of $matchcount): ", $Colors['snippet']) . 
								CleanColorText($snippet, $Colors['suspicious']) . 
								CleanColorText(": " . $s, $Colors['snippet']); 
			}
		}
	}
	
	# REPORT ALL THREAT MESSAGES AT ONCE, IF THERE WERE ANY.
	# TO PRINT EVERY FILENAME EXAMINED, MAKE THE THRESHOLD 0.
	if(count($OutputText) > 1)
	{
		foreach($OutputText as $s)
			echo $s;
		echo '<br><br>';
	}
	
} 

# --------------------------------------------------------------------------------
# THIS CODE ACTUALLY DOES THE SEARCH.

echo 
	"<br>" . 
	CleanColorText("Searching for files containing suspicious code or other text...", $Colors['status']) . 
	"<br>";

FindAndProcessFiles($StartPath, $FileMatchRegexes, $FullpathExcludeRegexes, 'FindMaliciousCodeSnippets');

# ================================================================================
# 4) SUSPICIOUS TIMESTAMPS.
# If you have found hacked files with timestamps showing that they were all modified
# at about the same date and time, you can use this routine to locate other files 
# that were modified at about the same time. 
# Define the suspicious time range in the User Configuration section near top of this script.
# Files with timestamps within that date/time range will be reported as suspicious.
# ================================================================================
# FILENAMES TO MATCH

$FileMatchRegexes = array
(
	'/\.htaccess$/i',
	'/\.php[45]?$/i',
	'/\.html?$/i',
	'/\.aspx?$/i',
	'/\.inc$/i',
	'/\.cfm$/i',
	'/\.js$/i',
	'/\.txt$/i',
	'/\.css$/i'
);
# AND FULLPATHS TO EXCLUDE FROM EXAMINATION

$FullpathExcludeRegexes = array
(
	'#lookforbadguys\.php$#i'
);

# --------------------------------------------------------------------------------
# HANDLER FUNCTION - THIS IS THE ACTION PERFORMED ON A FILE WHOSE NAME IS A MATCH.

function SuspiciousTimestamp($filename) 
{ 
	global $TimeRangeStart, $TimeRangeEnd, $Colors;

	$lastmod = date('Y-m-d H:i:s', filemtime($filename)); 
	if(($lastmod >= $TimeRangeStart) && ($lastmod <= $TimeRangeEnd))
	{
		echo 
			CleanColorText($lastmod . ' ', $Colors['timestamp']) . 
			CleanColorText($filename, $Colors['filename']) . 
			" has a " . 
			CleanColorText('suspicious timestamp', $Colors['suspicious']) . ".<br>"; 
	}
}   

# --------------------------------------------------------------------------------
# THIS CODE ACTUALLY DOES THE SEARCH.

# TO AVOID WASTING TIME WHEN A TIMESTAMP SEARCH ISN'T NEEDED,
# THIS CODE DOES NOT RUN UNTIL A MORE RECENT TIME WINDOW HAS BEEN DEFINED 
# IN THE USER CONFIGURATION SECTION NEAR THE TOP OF THE SCRIPT.

if(substr($TimeRangeStart, 0, 4) > '1980')
{
	echo 
		"<br>" . 
		CleanColorText("Searching for files with timestamps in the suspicious date/time range...", 
			$Colors['status']) .
		"<br>";

	FindAndProcessFiles($StartPath, $FileMatchRegexes, $FullpathExcludeRegexes, 'SuspiciousTimestamp'); 
}

# ================================================================================
# END OF THE SEARCH ROUTINES
# ================================================================================
# ================================================================================
# FUNCTION LIBRARY
# --------------------------------------------------------------------------------
# Output text in specified color, cleaning it with htmlentities().
# Malicious text snippets could by definition be hazardous, so 
# always use this to put text on the web page  
# unless it is going into a text (input) box or textarea.

function CleanColorText($text, $color)
{
	$outputcolor = 'black';
	$color = trim($color);
	if(preg_match('/^(red|blue|green|black|#[0-9A-F]{6})$/i', $color))
		$outputcolor = $color;
	return '<span style="color:' . $outputcolor . ';">' . htmlentities($text, ENT_QUOTES) . '</span>';
}

# --------------------------------------------------------------------------------

function ResetCounts()
{
	global $FilesCount, $FilesMatchedCount, 
			$DirectoriesCount, $DirectoriesMatchedCount, $AllFilesToProcess, $Colors;

	$FilesCount = $FilesMatchedCount = $DirectoriesCount = $DirectoriesMatchedCount = 0;
	$AllFilesToProcess = array();
}

# --------------------------------------------------------------------------------

function ShowCounts()
{
	global $FilesCount, $FilesMatchedCount, 
			$DirectoriesCount, $DirectoriesMatchedCount, $Colors;

	$s =	"Files encountered = $FilesCount" . ', ' . 
			"Matching regex and processed = $FilesMatchedCount" . '; ' . 
			"Directories encountered = $DirectoriesCount" . ', ' . 
			"Matched and processed = $DirectoriesMatchedCount";

	echo CleanColorText($s, $Colors['status']) . "<br>";
}

# --------------------------------------------------------------------------------
# Returns path translated to canonical absolute filesystem path,
# or FALSE if it fails (path does not exist or PHP cannot enter/read it).

function GetCanonicalPath($path)
{
	# CLEAN IT UP AND CONVERT TO STANDARD PHP FORMAT (/)
	$path = str_replace('\\', '/', $path); 
	$path = rtrim($path, '/'); 
	$path .= '/'; 

	$RealPath = realpath($path);	# FALSE IF PHP CANNOT READ ANY DIR IN HIERARCHY
	if($RealPath === FALSE)
		return FALSE;

	$RealPath = str_replace('\\', '/', $RealPath); 
	$RealPath = rtrim($RealPath, '/'); 
	$RealPath .= '/'; 

	return $RealPath;
}

# --------------------------------------------------------------------------------
/*
Recursively search the starting directory and all below it to find files whose names 
match the given regex(es).

Since this performs no action on the files found, it is now a generic file-finder 
like the Linux "find" command. You can do whatever you want with the list once it's built. 

$FileMatchRegexes can be either a string or an array. Passing them all at once 
allows the filesystem to be traversed only once to find all matches (20+% faster).
*/

function BuildFileList($StartDir, $FileMatchRegexes, $FullpathExcludeRegexes) 
{
	# NOTE THAT THIS FUNCTION REQUIRES THE GLOBAL VARIABLES DECLARED EARLIER.
	global $FilesCount, $FilesMatchedCount, 
			$DirectoriesCount, $DirectoriesMatchedCount, 
			$AllFilesToProcess, $Colors;

	# CHANGE BACKSLASHES TO FORWARD, WHICH IS OK IN PHP, EVEN IN WINDOWS.
	# THEN REMOVE ANY TRAILING SLASHES AND ADD EXACTLY ONE.
	$StartDir = str_replace('\\', '/', $StartDir); 
	$StartDir = rtrim($StartDir, '/'); 
	$StartDir .= '/'; 

	# ENSURE THAT THE CURRENT DIRECTORY EXISTS AND IS READABLE BY PHP.
	if(!is_dir($StartDir))
	{
		echo "Warning: Directory does not exist: " . CleanColorText($StartDir, $Colors['filename']) . "<br>";
		return;
	}
	$DirectoriesCount++;		# COUNT IT AS A DIRECTORY (READABLE OR NOT)
	if(!is_readable($StartDir))
	{
		echo CleanColorText("Warning: Directory is not readable by PHP: ", $Colors['suspicious']) . 
				CleanColorText($StartDir, $Colors['filename']) . 
				". Check its owner/group permissions.<br>";
		return;
	}

	# THE DIR IS READABLE, SO IT WILL BE PROCESSED.
	# A DIR IS NEVER ACTUALLY EXCLUDED FROM PROCESSING UNLESS IT CAN'T BE READ. 
	# ONLY FILES ARE AFFECTED BY THE EXCLUSION RULES.
	$DirectoriesMatchedCount++;	

	# IF THESE ARE NOT ARRAYS, TURN THEM INTO ARRAYS.
	if(!is_array($FileMatchRegexes))
		$FileMatchRegexes = array($FileMatchRegexes);
	if(!is_array($FullpathExcludeRegexes))
		$FullpathExcludeRegexes = array($FullpathExcludeRegexes);

	# DETERMINE IF EACH ENTRY IN THE CURRENT DIRECTORY IS A CANDIDATE FOR INCLUSION IN THE FILE LIST.
	$dir = dir($StartDir); 
	while(($filename = $dir->read()) !== FALSE) 
	{
		$fullname = $dir->path . $filename; 
		if(is_file($fullname))
		{
			$FilesCount++;	# ADD IT TO THE COUNT OF *ALL* FILES, PROCESSED OR NOT.

			# IF ITS NAME MATCHES ANY OF THE REGEXES, IT MIGHT GO INTO THE LIST...
			$matches = 0;
			foreach($FileMatchRegexes as $regex)
			{
				if(preg_match($regex, $filename))
				{
					$matches = 1;
					# UNLESS ITS FULLPATH MATCHES ANY OF THE EXCLUSION REGEXES.
					foreach($FullpathExcludeRegexes as $exclude)
					{
						if(preg_match($exclude, $fullname))
						{
							$matches = 0;
							break;
						}
					}
					break;
				}
			}
			if($matches)
			{ 
				$FilesMatchedCount++;
				$AllFilesToProcess[] = $fullname;
			}
		}
		else if(is_dir($fullname))
		{
			# ELSE IF IT IS A DIRECTORY AND NOT THE CURRENT ONE OR ITS PARENT,
			# RECURSIVELY CALL THIS FUNCTION TO PROCESS ALL *ITS* ENTRIES 
			# BEFORE CONTINUING WITH THE CURRENT DIRECTORY.

			if(($filename !== '.') && ($filename !== '..'))
				BuildFileList($fullname, $FileMatchRegexes, $FullpathExcludeRegexes); 
		}
	}
	$dir->close(); 
} 

# --------------------------------------------------------------------------------
# BUILD A MASTER LIST OF ALL THE FILES TO PROCESS,
# THEN SORT THE ARRAY AND PROCESS ALL ITS ENTRIES AT ONCE.

function FindAndProcessFiles($StartDir, $FileMatchRegexes, $FullpathExcludeRegexes, $FileHandlerFunction) 
{
	global $AllFilesToProcess;

	ResetCounts();	
	BuildFileList($StartDir, $FileMatchRegexes, $FullpathExcludeRegexes); 
	sort($AllFilesToProcess, SORT_STRING);
	foreach($AllFilesToProcess as $filename)
	{
		call_user_func($FileHandlerFunction, $filename); 
	}
	ShowCounts();
}

# --------------------------------------------------------------------------------
# END FUNCTION LIBRARY
# ================================================================================

echo "<br>" . CleanColorText("Done!", $Colors['status']) . "<br>"; 

?> 

</p> 
</body> 
</html>

