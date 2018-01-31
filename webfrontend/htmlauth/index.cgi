#!/usr/bin/perl

# Copyright 2018 Wörsty (git@loxberry.woerstenfeld.de)
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
# 
#     http://www.apache.org/licenses/LICENSE-2.0
# 
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.

use LoxBerry::System;
use LoxBerry::Web;
use LoxBerry::Log;
use MIME::Base64;
use CGI::Carp qw(fatalsToBrowser);
use CGI qw/:standard/;
use Config::Simple '-strict';
use warnings;
use strict;
no  strict "refs"; 

# Variables
my $maintemplatefilename 		= "cam_connect.html";
my $errortemplatefilename 		= "error.html";
my $successtemplatefilename 	= "success.html";
my $helptemplatefilename		= "help.html";
my $pluginconfigfile 			= "cam-connect.cfg";
my $languagefile 				= "language.ini";
my $logfile 					= "cam_connect.log";
my $template_title;
my $no_error_template_message	= "<b>Cam-Connect:</b> The error template is not readable. We must abort here. Please try to reinstall the plugin.";
my $version 					= "2.0.1";
my $helpurl 					= "http://www.loxwiki.eu/display/LOXBERRY/Cam-Connect";
my @pluginconfig_strings 		= ('LOGLEVEL','WATERMARK','EMAIL_USED','EMAIL_INLINE','EMAIL_TO','EMAIL_BODY','EMAIL_SIGNATURE','EMAIL_RESIZE','IMAGE_RESIZE','EMAIL_SUBJECT1','EMAIL_SUBJECT2','EMAIL_SUBJECT3','EMAIL_DATE_FORMAT','EMAIL_TIME_FORMAT','EMAIL_FROM_NAME','EMAIL_RECIPIENTS','EMAIL_FILENAME');
my $cam_model_list				= "";
my @lines						= [];	
my $log 						= LoxBerry::Log->new ( name => 'CamConnect', filename => $lbplogdir ."/". $logfile, append => 1 );
my $plugin_cfg 					= new Config::Simple($lbpconfigdir . "/" . $pluginconfigfile);
my %Config 						= $plugin_cfg->vars() if ( $plugin_cfg );
our $error_message				= "";

# Logging
if ( $plugin_cfg )
{
	$log->loglevel(int($Config{'default.LOGLEVEL'}));
	$LoxBerry::System::DEBUG 	= 1 if int($Config{'default.LOGLEVEL'}) eq 7;
	$LoxBerry::Web::DEBUG 		= 1 if int($Config{'default.LOGLEVEL'}) eq 7;
}
else
{
	$log->loglevel(7);
	$LoxBerry::System::DEBUG 	= 1;
	$LoxBerry::Web::DEBUG 		= 1;
}

LOGDEB "Init CGI and import names in namespace R::";
my $cgi 	= CGI->new;
$cgi->import_names('R');

if ( $R::delete_log )
{
	LOGDEB "Oh, it's a log delete call. ".$R::delete_log;
	LOGWARN "Delete Logfile: ".$logfile;
	print "Content-Type: text/plain\n\n";
	my $logfile = $log->close;
	system("/usr/bin/date > $logfile") or print "Failed";
	$log->open;
	LOGSTART "Logfile restarted.";
	exit;
}
else
{
	LOGDEB "No log delete call. Go ahead";
}

LOGDEB "Get language";
my $lang	= lblanguage();
LOGDEB "Resulting language is: " . $lang;

LOGDEB "Check, if filename for the errortemplate is readable";
stat($lbptemplatedir . "/" . $errortemplatefilename);
if ( !-r _ )
{
	LOGDEB "Filename for the errortemplate is not readable, that's bad";
	$error_message = $no_error_template_message;
	LoxBerry::Web::lbheader($template_title, $helpurl, $helptemplatefilename);
	print $error_message;
	LOGCRIT $error_message;
	LoxBerry::Web::lbfooter();
	LOGCRIT "Leaving Cam-Connect Plugin due to an unrecoverable error";
	exit;
}

LOGDEB "Filename for the errortemplate is ok, preparing template";
my $errortemplate = HTML::Template->new(
		filename => $lbptemplatedir . "/" . $errortemplatefilename,
		global_vars => 1,
		loop_context_vars => 1,
		die_on_bad_params=> 0,
		associate => $cgi,
		%htmltemplate_options,
		debug => 1,
		);
LOGDEB "Read error strings from " . $languagefile . " for language " . $lang;
my %ERR = LoxBerry::System::readlanguage($errortemplate, $languagefile);

LOGDEB "Check, if filename for the successtemplate is readable";
stat($lbptemplatedir . "/" . $successtemplatefilename);
if ( !-r _ )
{
	LOGDEB "Filename for the successtemplate is not readable, that's bad";
	$error_message = $ERR{'ERRORS.ERR_SUCCESS_TEMPLATE_NOT_READABLE'};
	&error;
}
LOGDEB "Filename for the successtemplate is ok, preparing template";
my $successtemplate = HTML::Template->new(
		filename => $lbptemplatedir . "/" . $successtemplatefilename,
		global_vars => 1,
		loop_context_vars => 1,
		die_on_bad_params=> 0,
		associate => $cgi,
		%htmltemplate_options,
		debug => 1,
		);
LOGDEB "Read success strings from " . $languagefile . " for language " . $lang;
my %SUC = LoxBerry::System::readlanguage($successtemplate, $languagefile);

LOGDEB "Check, if filename for the maintemplate is readable, if not raise an error";
$error_message = $ERR{'ERRORS.ERR_MAIN_TEMPLATE_NOT_READABLE'};
stat($lbptemplatedir . "/" . $maintemplatefilename);
&error if !-r _;
LOGDEB "Filename for the maintemplate is ok, preparing template";
my $maintemplate = HTML::Template->new(
		filename => $lbptemplatedir . "/" . $maintemplatefilename,
		global_vars => 1,
		loop_context_vars => 1,
		die_on_bad_params=> 0,
		%htmltemplate_options,
		debug => 1
		);
LOGDEB "Read main strings from " . $languagefile . " for language " . $lang;
my %L = LoxBerry::System::readlanguage($maintemplate, $languagefile);

LOGDEB "Check if plugin config file is readable";
if (!-r $lbpconfigdir . "/" . $pluginconfigfile) 
{
	LOGWARN "Plugin config file not readable.";
	LOGDEB "Check if config directory exists. If not, try to create it. In case of problems raise an error";
	$error_message = $ERR{'ERRORS.ERR_CREATE_CONFIG_DIRECTORY'};
	mkdir $lbpconfigdir unless -d $lbpconfigdir or &error; 
	LOGDEB "Try to create a default config";
	$error_message = $ERR{'ERRORS.ERR_CREATE CONFIG_FILE'};
	open my $configfileHandle, ">", $lbpconfigdir . "/" . $pluginconfigfile or &error;
 		print $configfileHandle 'WATERMARK="1"'."\n";
		print $configfileHandle 'EMAIL_USED="0"'."\n";
		print $configfileHandle 'EMAIL_INLINE="0"'."\n";
		print $configfileHandle 'EMAIL_TO="0"'."\n";
		print $configfileHandle 'EMAIL_BODY="Hallo,<br/>es wurde eben geklingelt. Anbei das Bild."'."\n";
		print $configfileHandle 'EMAIL_SIGNATURE="--<br/>Beste Gr&uuml;&szlig;e<br/>Dein LoxBerry"'."\n";
		print $configfileHandle 'EMAIL_RESIZE="0"'."\n";
		print $configfileHandle 'IMAGE_RESIZE="0"'."\n";
		print $configfileHandle 'EMAIL_SUBJECT1="Es wurde am"'."\n";
		print $configfileHandle 'EMAIL_SUBJECT2="um"'."\n";
		print $configfileHandle 'EMAIL_SUBJECT3="Uhr geklingelt!"'."\n";
		print $configfileHandle 'EMAIL_DATE_FORMAT="d.m.Y"'."\n";
		print $configfileHandle 'EMAIL_TIME_FORMAT="H:i:s"'."\n";
		print $configfileHandle 'EMAIL_FROM_NAME="LoxBerry"'."\n";
		print $configfileHandle 'EMAIL_RECIPIENTS="noreply@loxberry.de;invalid@loxberry.de"'."\n";
		print $configfileHandle 'EMAIL_FILENAME="Snapshot"'."\n";
		print $configfileHandle 'LOGLEVEL="2"'."\n";
	close $configfileHandle;
	LOGWARN "Default config created. Display error anyway to force a page reload";
	$error_message = $ERR{'ERRORS.ERR_NO_CONFIG_FILE'};
	&error; 
}

LOGDEB "Parsing valid config variables into the maintemplate";
foreach my $config_value (@pluginconfig_strings)
{
	${$config_value} = $Config{'default.' . $config_value};
	if (defined ${$config_value} && ${$config_value} ne '') 
	{
		LOGDEB "Set config variable: " . $config_value . " to " . ${$config_value};
  		$maintemplate->param($config_value	, ${$config_value} );
	}                                  	                             
	else
	{
		LOGWARN "Config variable: " . $config_value . " missing or empty.";     
  		$maintemplate->param($config_value	, "");
	}	                                                                
}    

LOGDEB "Parsing special parameters into the maintemplate";
foreach my $parameter_to_process ('WATERMARK','EMAIL_INLINE','EMAIL_TO')
{
	if ( int(${$parameter_to_process}) eq 1 ) 
	{
	    $maintemplate->param($parameter_to_process . "_script", '$("#'.$parameter_to_process.'_checkbox").prop("checked", 1);');
	    ${$parameter_to_process} = 1;
	}
	else
	{
	    $maintemplate->param($parameter_to_process . "_script", '$("#'.$parameter_to_process.'_checkbox").prop("checked", 0);');
	    ${$parameter_to_process} = 0;
	}
	LOGDEB "Set special parameter " . $parameter_to_process . " to " . ${$parameter_to_process};
}

$R::saveformdata if 0; # Prevent errors
LOGDEB "Is it a save call?";
if ( $R::saveformdata ) 
{
	LOGDEB "Yes, is it a save call";
	foreach my $parameter_to_write (@pluginconfig_strings)
	{
	    while (my ($config_variable, $value) = each %R::) 
	    {
			if ( $config_variable eq $parameter_to_write )
			{
				$plugin_cfg->param($config_variable, ${$value});		
				LOGDEB "Setting configuration variable [$config_variable] to value (${$value}) ";
			}
		}
	}
	LOGDEB "Write config to file";
	$error_message = $ERR{'ERRORS.ERR_SAVE_CONFIG_FILE'};
	$plugin_cfg->save() or &error; 

	LOGDEB "Set page title, load header, parse variables, set footer, end";
	$template_title = " : " . $SUC{'SAVE.MY_NAME'};
	LoxBerry::Web::lbheader($template_title, $helpurl, $helptemplatefilename);
	$successtemplate->param('SAVE_ALL_OK'		, $SUC{'SAVE.SAVE_ALL_OK'});
	$successtemplate->param('SAVE_MESSAGE'		, $SUC{'SAVE.SAVE_MESSAGE'});
	$successtemplate->param('SAVE_BUTTON_OK' 	, $SUC{'SAVE.SAVE_BUTTON_OK'});
	$successtemplate->param('SAVE_NEXTURL'		, $ENV{REQUEST_URI});
	print $successtemplate->output();
	LoxBerry::Web::lbfooter();
	LOGDEB "Leaving Cam-Connect Plugin after saving the configuration.";
	exit;
}
else
{
	LOGDEB "No, not a save call";
}
LOGDEB "Call default page";
&defaultpage;

#####################################################
# Subs
#####################################################

sub defaultpage 
{
	LOGDEB "Sub defaultpage";
	LOGDEB "Prepare Cam list";
	$cam_model_list="";
	open(F,"$lbpconfigdir/camera_models.dat") || die "Missing camera list.";
	 flock(F,2);
	 @lines = <F>;
	 flock(F,8);
	close(F);
	foreach (@lines)
	{
	  s/[\n\r]//g;
	  our @cams = split /\|/, $_;
	    $cam_model_list = "$cam_model_list\n<option value=\"$cams[0]\">$cams[1] $cams[2]</option>\n";
		LOGDEB "Adding cam model: #" . $cams[0] . " " . $cams[1] . " (" . $cams[2] . ")";
	}
	LOGDEB "Set page title, load header, parse variables, set footer, end";
	$template_title = " : " . $L{'CC.MY_NAME'};
	LoxBerry::Web::lbheader($template_title, $helpurl, $helptemplatefilename);
	$maintemplate->param( "CC.LOGO_ICON", get_plugin_icon(64) );
	$maintemplate->param( "HTTP_HOST"		, $ENV{HTTP_HOST});
	$maintemplate->param( "HTTP_PATH"		, "/plugins/" . $lbpplugindir);
	$maintemplate->param( "cam_model_list"	, $cam_model_list);
	$lbplogdir =~ s/$lbhomedir\/log\///; # Workaround due to missing variable for Logview
	$maintemplate->param( "LOGFILE" , $lbplogdir . "/" . $logfile );
	my $notifications = LoxBerry::Log::get_notifications_html($lbpplugindir, $lbpplugindir);
	LOGDEB "Check for pending notifications for: " . $lbpplugindir . " " . ${'CC.MY_NAME'};
	LOGDEB "Notifications are: ".$notifications;
    $maintemplate->param( "NOTIFICATIONS" , $notifications);
    print $maintemplate->output();
	LoxBerry::Web::lbfooter();
	LOGDEB "Leaving Cam-Connect Plugin normally";
	exit;
}

sub error 
{
	LOGDEB "Sub error";
	LOGERR $error_message;
	LOGDEB "Set page title, load header, parse variables, set footer, end with error";
	$template_title = " : " . $ERR{'ERRORS.MY_NAME'} . " - " . $ERR{'ERRORS.ERR_TITLE'};
	LoxBerry::Web::lbheader($template_title, $helpurl, $helptemplatefilename);
	$errortemplate->param('ERR_MESSAGE'		, $error_message);
	$errortemplate->param('ERR_TITLE'		, $ERR{'ERRORS.ERR_TITLE'});
	$errortemplate->param('ERR_BUTTON_BACK' , $ERR{'ERRORS.ERR_BUTTON_BACK'});
	$successtemplate->param('ERR_NEXTURL'	, $ENV{REQUEST_URI});
	print $errortemplate->output();
	LoxBerry::Web::lbfooter();
	LOGDEB "Leaving Cam-Connect Plugin with an error";
	exit;
}
