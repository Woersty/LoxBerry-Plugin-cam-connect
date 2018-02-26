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
use List::MoreUtils 'true','minmax';
use HTML::Entities;
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
my $version 					= LoxBerry::System::pluginversion();
my $helpurl 					= "http://www.loxwiki.eu/display/LOXBERRY/Cam-Connect";
my @pluginconfig_strings 		= ('EMAIL_FILENAME');
my @pluginconfig_cameras 		= ("CAM_HOST_OR_IP","CAM_PORT","CAM_MODEL","CAM_USER","CAM_PASS","CAM_NOTE","CAM_RECIPIENTS","CAM_NAME","CAM_EMAIL_FROM_NAME","CAM_EMAIL_SUBJECT1","CAM_EMAIL_DATE_FORMAT","CAM_EMAIL_SUBJECT2","CAM_EMAIL_TIME_FORMAT","CAM_EMAIL_SUBJECT3","CAM_EMAIL_BODY","CAM_EMAIL_SIGNATURE","CAM_IMAGE_RESIZE","CAM_EMAIL_RESIZE","CAM_NO_EMAIL_CB","CAM_WATERMARK_CB","CAM_EMAIL_USED_CB","CAM_EMAIL_MULTIPICS","CAM_EMAIL_INLINE_CB");
my $cam_model_list				= "";
my @lines						= [];	
my $log 						= LoxBerry::Log->new ( name => 'CamConnect', filename => $lbplogdir ."/". $logfile, append => 1 );
my $plugin_cfg 					= new Config::Simple($lbpconfigdir . "/" . $pluginconfigfile);
my %Config 						= $plugin_cfg->vars() if ( $plugin_cfg );
our $error_message				= "";

# Logging
my $plugin = LoxBerry::System::plugindata();

LOGSTART "New admin call."      if $plugin->{PLUGINDB_LOGLEVEL} eq 7;
$LoxBerry::System::DEBUG 	= 1 if $plugin->{PLUGINDB_LOGLEVEL} eq 7;
$LoxBerry::Web::DEBUG 		= 1 if $plugin->{PLUGINDB_LOGLEVEL} eq 7;
$log->loglevel($plugin->{PLUGINDB_LOGLEVEL});

LOGDEB "Init CGI and import names in namespace R::";
my $cgi 	= CGI->new;
$cgi->import_names('R');

if ( $R::delete_log )
{
	LOGDEB "Oh, it's a log delete call. ".$R::delete_log;
	LOGWARN "Delete Logfile: ".$logfile;
	my $logfile = $log->close;
	system("/usr/bin/date > $logfile");
	$log->open;
	LOGSTART "Logfile restarted.";
	print "Content-Type: text/plain\n\nOK";
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
		print $configfileHandle 'EMAIL_FILENAME="Snapshot"'."\n";
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
$maintemplate->param( "LBPPLUGINDIR" , $lbpplugindir);

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

	my @matches = grep { /CAM_HOST_OR_IP[0-9]*/ } %R::;
	s/CAM_HOST_OR_IP// for @matches ;
 
	foreach my $cameras (@matches)
	{
	LOGDEB "Prepare camera $cameras config:";
		foreach my $cam_parameter_to_write (@pluginconfig_cameras)
		{

		    while (my ($cam_config_variable, $cam_value) = each %R::) 
		    {
				if ( $cam_config_variable eq $cam_parameter_to_write . $cameras )
				{
					if (defined ${$cam_value} && ${$cam_value} ne '') 
					{
						LOGDEB "Setting configuration variable [".$cam_config_variable . "] to value (" . ${$cam_value} .")";
						$plugin_cfg->param($cam_config_variable , ${$cam_value});		
					}
					else
					{
						LOGDEB "Config variable: " . $cam_config_variable . " missing or empty. Ignoring it.";     
					}	 
				}
			}
		}
	}
	$plugin_cfg->param('VERSION', $version);		
	LOGDEB "Write config to file";
	$error_message = $ERR{'ERRORS.ERR_SAVE_CONFIG_FILE'};
	$plugin_cfg->save() or &error; 

	LOGDEB "Set page title, load header, parse variables, set footer, end";
	$template_title = $SUC{'SAVE.MY_NAME'};
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
	open(F,"$lbpdatadir/camera_models.dat") || die "Missing camera list.";
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
	$template_title = $L{'CC.MY_NAME'};
	LoxBerry::Web::lbheader($template_title, $helpurl, $helptemplatefilename);
	$maintemplate->param( "CC.LOGO_ICON", get_plugin_icon(64) );
	$maintemplate->param( "HTTP_HOST"		, $ENV{HTTP_HOST});
	$maintemplate->param( "HTTP_PATH"		, "/plugins/" . $lbpplugindir);
	$maintemplate->param( "cam_model_list"	, $cam_model_list);
	$maintemplate->param( "VERSION"			, $version);
	$maintemplate->param( "LOGLEVEL" 		, $L{"CC.LOGLEVEL".$plugin->{PLUGINDB_LOGLEVEL}});
	$lbplogdir =~ s/$lbhomedir\/log\///; # Workaround due to missing variable for Logview
	$maintemplate->param( "LOGFILE" , $lbplogdir . "/" . $logfile );
	LOGDEB "Check for pending notifications for: " . $lbpplugindir . " " . $L{'CC.MY_NAME'};
	my $notifications = LoxBerry::Log::get_notifications_html($lbpplugindir, $L{'CC.MY_NAME'});
	LOGDEB "Notifications are:\n".encode_entities($notifications) if $notifications;
	LOGDEB "No notifications pending." if !$notifications;
    $maintemplate->param( "NOTIFICATIONS" , $notifications);
	my @camdata = ();
	my @known_cams = grep { /CAM_HOST_OR_IP[0-9]*/ } %Config;
	s/default.CAM_HOST_OR_IP// for @known_cams;
	@known_cams = sort @known_cams;
	LOGDEB "Found following cameras in config: ".join(",",@known_cams);
	my ($first_cam_id, $last_cam_id) = minmax @known_cams;
	if ( "$first_cam_id" eq "" )
	{
		$maintemplate->param( "NOCAMS", 1);
	}
	else
	{
		$maintemplate->param( "SOMECAMS", 1);
	}
	if ( $R::create_cam )
	{
		LOGDEB "Oh, it's a create_cam call. ";
		LOGDEB "Create new camera: ".$last_cam_id;
		$error_message = $ERR{'ERRORS.ERR_CREATE_CONFIG_FILE'};
		open my $configfileHandle, ">>", $lbpconfigdir . "/" . $pluginconfigfile or &error;
			my $last_cam_id = $last_cam_id + 1;
			print $configfileHandle 'CAM_IMAGE_RESIZE'.$last_cam_id.'=9999'."\n";
			print $configfileHandle 'CAM_EMAIL_RESIZE'.$last_cam_id.'=9999'."\n";
			print $configfileHandle 'CAM_HOST_OR_IP'.$last_cam_id.'="'.$L{'CAM_HOST_SUGGESTION'}.'"'."\n";
			print $configfileHandle 'CAM_PORT'.$last_cam_id.'="'.$L{'CAM_PORT_SUGGESTION'}.'"'."\n";
			print $configfileHandle 'CAM_USER'.$last_cam_id.'="'.$L{'CAM_USER_SUGGESTION'}.'"'."\n";
			print $configfileHandle 'CAM_PASS'.$last_cam_id.'=""'."\n";
			print $configfileHandle 'CAM_NAME'.$last_cam_id.'="'.$L{'CAM_NAME_SUGGESTION'}.'"'."\n";
			print $configfileHandle 'CAM_EMAIL_FROM_NAME'.$last_cam_id.'="'.$L{'CAM_EMAIL_FROM_NAME_SUGGESTION'}.'"'."\n";
			print $configfileHandle 'CAM_NO_EMAIL_CB'.$last_cam_id."=0\n";
			print $configfileHandle 'CAM_EMAIL_INLINE_CB'.$last_cam_id."=0\n";
			print $configfileHandle 'CAM_EMAIL_MULTIPICS'.$last_cam_id.'="10"'."\n";
			print $configfileHandle 'CAM_WATERMARK_CB'.$last_cam_id."=0\n";
			print $configfileHandle 'CAM_EMAIL_USED_CB'.$last_cam_id."=0\n";
			print $configfileHandle 'CAM_NOTE'.$last_cam_id.'="'.$L{'CAM_NOTE_SUGGESTION'}.'"'."\n";
			print $configfileHandle 'CAM_RECIPIENTS'.$last_cam_id.'="'.$L{'CAM_RECIPIENTS_SUGGESTION'}.'"'."\n";
			print $configfileHandle 'CAM_MODEL'.$last_cam_id.'=1'."\n";
		close $configfileHandle;
		print "Content-Type: text/plain\n\n";
		print "OK\n";
		exit;
	}
	else
	{
		LOGDEB "No create_cam call. Go ahead";
	}
	if ( $R::delete_cam )
	{
		LOGDEB "Oh, it's a delete_cam call. ";
		LOGDEB "Delete camera: ".$R::delete_cam;
		$error_message = $ERR{'ERRORS.ERR_CREATE_CONFIG_FILE'};
		use Tie::File;
		print "Content-Type: text/plain\n\n";
		my $cam_param_to_delete = "";
		foreach my $param_to_delete (@pluginconfig_cameras)
	    {
			my $cam_param_to_delete = $param_to_delete.$R::delete_cam."=";
			LOGDEB "Delete cam parameter: ".$cam_param_to_delete;
			tie my @file_lines, 'Tie::File', $lbpconfigdir . "/" . $pluginconfigfile or die;
			@file_lines = grep !/^$cam_param_to_delete/, @file_lines;
			untie @file_lines or die "$!";
		}
		print "OK\n";
		exit;
	}
	else
	{
		LOGDEB "No delete_cam call. Go ahead";
	}

    foreach my $camno (@known_cams)
    {	
		my %cam;

		my @fill_suggestions = ("CAM_EMAIL_SUBJECT1","CAM_EMAIL_SUBJECT2","CAM_EMAIL_SUBJECT3","CAM_EMAIL_DATE_FORMAT","CAM_EMAIL_TIME_FORMAT","CAM_EMAIL_BODY","CAM_EMAIL_SIGNATURE");
    	foreach my $suggestion_field (@fill_suggestions)
	    {	
			if (!defined $plugin_cfg->param( $suggestion_field . $camno ) ) 
			{
				LOGDEB "Setting suggested CAM configuration variable [" . $suggestion_field . "] to value (" . $L{ "CC." . $suggestion_field . "_SUGGESTION" } . ")";
				$cam{$suggestion_field}	= uri_unescape($L{ "CC." . $suggestion_field . "_SUGGESTION" });
			}
			else
			{
				LOGDEB "Setting CAM configuration variable [" . $suggestion_field . $camno . "] to value (" . $L{ "CC." . $suggestion_field . "_SUGGESTION" } . ")";
				$cam{$suggestion_field}	= uri_unescape($plugin_cfg->param($suggestion_field . $camno));
			}
		}
		$cam{CAMNO} = $camno;
		$cam{CAM_HOST_OR_IP} 		= $plugin_cfg->param("CAM_HOST_OR_IP".$camno);
		$cam{CAM_PORT} 				= $plugin_cfg->param("CAM_PORT".$camno);
		$cam{CAM_MODEL} 			= $plugin_cfg->param("CAM_MODEL".$camno);
		$cam{CAM_USER} 				= uri_unescape($plugin_cfg->param("CAM_USER".$camno));
		$cam{CAM_PASS} 				= uri_unescape($plugin_cfg->param("CAM_PASS".$camno));
		$cam{CAM_NOTE} 				= uri_unescape($plugin_cfg->param("CAM_NOTE".$camno));
		$cam{CAM_RECIPIENTS} 		= uri_unescape($plugin_cfg->param("CAM_RECIPIENTS".$camno));
		$cam{CAM_NAME} 				= uri_unescape($plugin_cfg->param("CAM_NAME".$camno));
		$cam{CAM_EMAIL_FROM_NAME}	= uri_unescape($plugin_cfg->param("CAM_EMAIL_FROM_NAME".$camno));
		$cam{CAM_IMAGE_RESIZE} 		= $plugin_cfg->param("CAM_IMAGE_RESIZE".$camno);
		$cam{CAM_EMAIL_RESIZE} 		= $plugin_cfg->param("CAM_EMAIL_RESIZE".$camno);
		$cam{CAM_EMAIL_MULTIPICS} 	= $plugin_cfg->param("CAM_EMAIL_MULTIPICS".$camno);
		foreach my $cam_parameter_to_process ('CAM_NO_EMAIL_CB','CAM_EMAIL_INLINE_CB','CAM_WATERMARK_CB','CAM_EMAIL_USED_CB')
		{
			if ( int($plugin_cfg->param($cam_parameter_to_process . $camno)) eq 1 ) 
			{
				$cam{$cam_parameter_to_process} = 1; 
			    $cam{$cam_parameter_to_process. "_script"} = '$("#'.$cam_parameter_to_process . '_checkbox'.$camno .'").prop("checked", 1);';
			}
			else
			{
				$cam{$cam_parameter_to_process} = 0; 
			    $cam{$cam_parameter_to_process. "_script"}  = '	$("#'.$cam_parameter_to_process . '_checkbox'.$camno.'").prop("checked", 0);';
			}
			$cam{$cam_parameter_to_process. "_script"}  = $cam{$cam_parameter_to_process. "_script"} . '
			$("#'.$cam_parameter_to_process . '_checkbox'.$camno.'").on("change", function(event) 
			{ 
				if ( $("#'.$cam_parameter_to_process . '_checkbox'.$camno.'").is(":checked") ) 
				{ 
					$("#'.$cam_parameter_to_process . $camno.'").val(1); 
					$("label[for=\''.$cam_parameter_to_process . '_checkbox'.$camno.'\']" ).removeClass( "ui-checkbox-off" ).addClass( "ui-checkbox-on" );
				} 
				else 
				{ 
					$("#'.$cam_parameter_to_process . $camno.'").val(0); 
					$("label[for=\''.$cam_parameter_to_process . '_checkbox'.$camno.'\']" ).removeClass( "ui-checkbox-on" ).addClass( "ui-checkbox-off" );
				}
			});
			$("#'.$cam_parameter_to_process . '_checkbox' .$camno.'").trigger("change");';
		
			
			LOGDEB "Set special parameter " . $cam_parameter_to_process . $camno;
		}
		push(@camdata, \%cam);
	}
	$maintemplate->param("CAMDATA" => \@camdata);
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
	$template_title = $ERR{'ERRORS.MY_NAME'} . " - " . $ERR{'ERRORS.ERR_TITLE'};
	LoxBerry::Web::lbheader($template_title, $helpurl, $helptemplatefilename);
	$errortemplate->param('ERR_MESSAGE'		, $error_message);
	$errortemplate->param('ERR_TITLE'		, $ERR{'ERRORS.ERR_TITLE'});
	$errortemplate->param('ERR_BUTTON_BACK' , $ERR{'ERRORS.ERR_BUTTON_BACK'});
	print $errortemplate->output();
	LoxBerry::Web::lbfooter();
	LOGDEB "Leaving Cam-Connect Plugin with an error";
	exit;
}
