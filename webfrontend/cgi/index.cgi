#!/usr/bin/perl

# Copyright 2016 Michael Schlenstedt, michael@loxberry.de
# + Wörsty (git@loxberry.woerstenfeld.de)
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


##########################################################################
# Modules
##########################################################################

use CGI::Carp qw(fatalsToBrowser);
use CGI qw/:standard/;
use Config::Simple;
use File::HomeDir;
use HTML::Entities;
use Data::Dumper;
use Cwd 'abs_path';
use warnings;
use strict;
no strict "refs"; # we need it for template system

##########################################################################
# Variables
##########################################################################

our $cfg;
our $phrase;
our $namef;
our $value;
our %query;
our $lang;
our $template_title;
our $help;
our @help;
our $helptext;
our $helplink;
our $installfolder;
our $languagefile;
our $version;
my  $home = File::HomeDir->my_home;
my  $subfolder;
my  $cgi = new CGI;
our $languagefileplugin;
our $phraseplugin;
our @language_strings;
our @pluginconfig_strings;
our $self_host;
our $plugin_script;
our $plugin_cfg;
our $WATERMARK;
our $saveformdata;
our $plugin_name;
our $message;
our $nexturl;
our $pluginconfigdir;
our $pluginconfigfile;
our $cam_model_list;
our @lines;
our $psubfolder;
our $EMAIL_USED=0;
our $EMAIL_TO=0;
our $EMAIL_INLINE=1;
our $EMAIL_BODY="";
our $EMAIL_SIGNATURE="";
our $EMAIL_RESIZE;
our $EMAIL_SUBJECT1="";
our $EMAIL_SUBJECT2="";
our $EMAIL_SUBJECT3="";
our $EMAIL_DATE_FORMAT="";
our $EMAIL_TIME_FORMAT="";
our $EMAIL_FROM_NAME="";
our $EMAIL_RECIPIENTS="";
our $EMAIL_FILENAME="Snapshot";
our $error;
##########################################################################
# Read Settings
##########################################################################

# Version of this script
$version = "0.0.4";

# Figure out in which subfolder we are installed
$psubfolder = abs_path($0);
$psubfolder =~ s/(.*)\/(.*)\/(.*)$/$2/g;
$pluginconfigdir  = "$home/config/plugins/$psubfolder";
$pluginconfigfile = "$pluginconfigdir/cam-connect.cfg";

$cfg              = new Config::Simple("$home/config/system/general.cfg");
$installfolder    = $cfg->param("BASE.INSTALLFOLDER");
$lang             = $cfg->param("BASE.LANG");

# If there's no plugin config file create default
if (!-r $pluginconfigfile) 
{
	mkdir $pluginconfigdir unless -d $pluginconfigdir; # Check if dir exists. If not create it.
	open my $configfileHandle, ">", "$pluginconfigfile" or die "Can't create '$pluginconfigfile'\n";
	print $configfileHandle 'WATERMARK=1'."\n";
	print $configfileHandle 'EMAIL_USED=0'."\n";
	print $configfileHandle 'EMAIL_INLINE=0'."\n";
	print $configfileHandle 'EMAIL_TO=0'."\n";
	print $configfileHandle 'EMAIL_BODY=Hallo,<br/>es wurde eben geklingelt. Anbei das Bild.'."\n";
	print $configfileHandle 'EMAIL_SIGNATURE=--<br/>Beste Gr&uuml;&szlig;e<br/>Dein LoxBerry'."\n";
	print $configfileHandle 'EMAIL_RESIZE=720';
	print $configfileHandle 'EMAIL_SUBJECT1=Es wurde am '."\n";
	print $configfileHandle 'EMAIL_SUBJECT2= um '."\n";
	print $configfileHandle 'EMAIL_SUBJECT3= geklingelt!'."\n";
	print $configfileHandle 'EMAIL_DATE_FORMAT=d.m.Y'."\n";
	print $configfileHandle 'EMAIL_TIME_FORMAT=H:i:s \U\h\r '."\n";
	print $configfileHandle 'EMAIL_FROM_NAME=LoxBerry'."\n";
	print $configfileHandle 'EMAIL_RECIPIENTS=noreply@loxberry.de;invalid@loxberry.de'."\n";
	print $configfileHandle 'EMAIL_FILENAME=Snapshot'."\n";
	close $configfileHandle;
}

# Read configfile
if (open(my $fh, '<', $pluginconfigfile)) 
{
  while (my $row = <$fh>) 
  {
    chomp $row;
		foreach ($row)
		{
			if ( substr(($row),0,1) eq ";" )
			{
				# Ignore comments
			}
			else
			{
				my @configs = split /=/, $_,2;
		  	${@configs[0]} = $configs[1];
				push @pluginconfig_strings, @configs[0];
			}
		}
  }
}
else
{
	$error = "Could not open Plugin Configfile";
	&error; 
}

# Everything from URL
foreach (split(/&/,$ENV{'QUERY_STRING'})){
  ($namef,$value) = split(/=/,$_,2);
  $namef =~ tr/+/ /;
  $namef =~ s/%([a-fA-F0-9][a-fA-F0-9])/pack("C", hex($1))/eg;
  $value =~ tr/+/ /;
  $value =~ s/%([a-fA-F0-9][a-fA-F0-9])/pack("C", hex($1))/eg;
  $query{$namef} = $value;
}

##########################################################################
# Language Settings
##########################################################################

# Override settings with URL param
if ($query{'lang'}) {
  $lang = $query{'lang'};
}

# Standard is german
if ($lang eq "") {
  $lang = "de";
}

# If there's no language phrases file for choosed language, use german as default
if (!-e "$installfolder/templates/system/$lang/language.dat") {
  $lang = "de";
}

# Read system translations / phrases
$languagefile = "$installfolder/templates/system/$lang/language.dat";
$phrase = new Config::Simple($languagefile);

# Read plugin translations / phrases
$languagefileplugin = "$installfolder/templates/plugins/$psubfolder/$lang/language.dat";
$phraseplugin = new Config::Simple($languagefileplugin);
# Create @language_strings array with all known phrase-names
foreach (keys$phraseplugin->vars())
{
	(my $cfg_section,my $cfg_varname) = split(/\./,$_,2);
	push @language_strings, $cfg_varname;
}

$template_title = $phrase->param("TXT0000") . ": " . $phraseplugin->param("TXT0000");
$self_host =$cgi->server_name();
$plugin_script = "/plugins/$psubfolder/";

##########################################################################
# Plugin Settings
##########################################################################

# Process checkboxes
foreach my $parameter_to_process ('WATERMARK','EMAIL_USED','EMAIL_INLINE','EMAIL_TO')
{
	if ( int(${$parameter_to_process}) eq 1 ) 
	{
	    ${$parameter_to_process."_script"} = '$("#'.$parameter_to_process.'_checkbox").prop("checked", 1);';
	    ${$parameter_to_process} = 1;
	}
	else
	{
	    ${$parameter_to_process."_script"} = '$("#'.$parameter_to_process.'_checkbox").prop("checked", 0);';
	    ${$parameter_to_process} = 0;
	}
}

# Process size dropdown
${"EMAIL_RESIZE_".$EMAIL_RESIZE}=" selected ";

$plugin_name = $phraseplugin->param("TXT0000");

###############################
###########################################
# Main program
##########################################################################


		# Form Save?
		
		if ( param('saveformdata') )
		{
		$saveformdata = param('saveformdata');
		if ($saveformdata eq "") 
		{
			$saveformdata = 0;
		}
		else
		{
		 $saveformdata      = 1;
		}
		}
		else
		{
		$saveformdata = 0;
		}
		
		if ($saveformdata == 1)
		{
		# Write configuration file

		open my $configfileHandle, ">", "$pluginconfigfile" or die "Can't create '$pluginconfigfile'\n";
		foreach my $parameter_to_write (@pluginconfig_strings)
		{
			print $configfileHandle $parameter_to_write.'='.param($parameter_to_write)."\n";
		}
		close $configfileHandle;
		
		
		
		
		print "Content-Type: text/html\n\n";
		$template_title = $phrase->param("TXT0000") . ": " . $phraseplugin->param("MY_NAME");;
		$message = $phraseplugin->param("CFG_SAVED");
		$nexturl = "javascript:history.back();";
		
		# Print Template
		&lbheader;
		open(F,"$installfolder/templates/system/$lang/success.html") || die "Missing template system/$lang/succses.html";
		while (<F>) {
		  $_ =~ s/<!--\$(.*?)-->/${$1}/g;
		  print $_;
		}
		close(F);
		&footer;
		exit;
		}

&defaultpage;

exit;

#####################################################
# Form
#####################################################

sub defaultpage {


# Prepare Cams
$cam_model_list="";
open(F,"$installfolder/config/plugins/$psubfolder/camera_models.dat") || die "Missing camera list.";
 flock(F,2);
 @lines = <F>;
 flock(F,8);
close(F);
foreach (@lines){
  s/[\n\r]//g;
  our @cams = split /\|/, $_;
    $cam_model_list = "$cam_model_list\n<option value=\"$cams[0]\">$cams[1] $cams[2]</option>\n";
}


print "Content-Type: text/html\n\n";

# Print Template
&lbheader;

# Parse the strings we want
foreach our $template_string (@language_strings)
{
		${$template_string} = $phraseplugin->param($template_string);
}
	
open(F,"$installfolder/templates/plugins/$psubfolder/$lang/settings.html") || die "Missing template plugins/cam-connect/$lang/settings.html";
  while (<F>) {
    $_ =~ s/<!--\$(.*?)-->/${$1}/g;
    print $_;
  }

  
close(F);
&footer;

exit;

}

exit;


#####################################################
# 
# Subroutines
#
#####################################################

#####################################################
# Error
#####################################################

sub error {

$template_title = $phrase->param("TXT0000") . ": " . $phraseplugin->param("TXT0000") . " - " . $phrase->param("TXT0028");

print "Content-Type: text/html\n\n";

&lbheader;
open(F,"$installfolder/templates/system/$lang/error.html") || die "Missing template system/$lang/error.html";
    while (<F>) {
      $_ =~ s/<!--\$(.*?)-->/${$1}/g;
      print $_;
    }
close(F);
&footer;

exit;

}

#####################################################
# Header
#####################################################

sub lbheader {

  # create help page
  $helplink = "http://www.loxwiki.eu/display/LOXBERRY/Cam-Connect";
  $helptext = "";
   open(F,"$installfolder/templates/plugins/$psubfolder/$lang/help.html") || die "Missing template plugins/miniserverbackup/$lang/help.html";
    @help = <F>;
    foreach (@help){
      s/[\n\r]/ /g;
      $helptext = $helptext . $_;
    }
  close(F);

  open(F,"$installfolder/templates/system/$lang/header.html") || die "Missing template system/$lang/header.html";
    while (<F>) {
      $_ =~ s/<!--\$(.*?)-->/${$1}/g;
      print $_;
    }
  close(F);

}

#####################################################
# Footer
#####################################################

sub footer {

  open(F,"$installfolder/templates/system/$lang/footer.html") || die "Missing template system/$lang/footer.html";
    while (<F>) {
      $_ =~ s/<!--\$(.*?)-->/${$1}/g;
      print $_;
    }
  close(F);

}

