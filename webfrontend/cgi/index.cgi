#!/usr/bin/perl

# Copyright 2016 Michael Schlenstedt, michael@loxberry.de
# + W�rsty (git@loxberry.woerstenfeld.de)
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
our $self_host;
our $plugin_script;
our $plugin_cfg;
our $plugin_watermark;
our $saveformdata;
our $plugin_watermark_label;
our $plugin_name;
our $message;
our $nexturl;
our $pluginconfigdir;
our $pluginconfigfile;
our $cam_model_list;
our @lines;
our $psubfolder;

##########################################################################
# Read Settings
##########################################################################

# Version of this script
$version = "0.0.3";

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
	open my $configfileHandle, ">>", "$pluginconfigfile" or die "Can't open '$pluginconfigfile'\n";
	print $configfileHandle "WATERMARK=1\n";
	close $configfileHandle;
}
  $plugin_cfg       = new Config::Simple($pluginconfigfile);
	$plugin_watermark = $plugin_cfg->param("WATERMARK");

# Everything from URL
foreach (split(/&/,$ENV{'QUERY_STRING'})){
  ($namef,$value) = split(/=/,$_,2);
  $namef =~ tr/+/ /;
  $namef =~ s/%([a-fA-F0-9][a-fA-F0-9])/pack("C", hex($1))/eg;
  $value =~ tr/+/ /;
  $value =~ s/%([a-fA-F0-9][a-fA-F0-9])/pack("C", hex($1))/eg;
  $query{$namef} = $value;
}

if ( param('plugin_watermark') )
{
	$plugin_watermark = param('plugin_watermark');
	if ($plugin_watermark eq "on" || $plugin_watermark eq "1" || $plugin_watermark eq "true") 
	{
		$plugin_watermark = 1;
	}
	else
	{
		$plugin_watermark = 0;
  }
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

# Read translations / phrases
$languagefile = "$installfolder/templates/system/$lang/language.dat";
$phrase = new Config::Simple($languagefile);

$languagefileplugin = "$installfolder/templates/plugins/$psubfolder/$lang/language.dat";
$phraseplugin = new Config::Simple($languagefileplugin);

$template_title = $phrase->param("TXT0000") . ": " . $phraseplugin->param("TXT0000");
$self_host =$cgi->server_name();
$plugin_script = "/plugins/$psubfolder/";

##########################################################################
# Plugin Settings
##########################################################################

if ($plugin_watermark eq 1 || $plugin_watermark eq "on" || $plugin_watermark eq "true") 
{
    $plugin_watermark      	 = '$("#plugin_watermark").prop("checked", 1);';
    $plugin_watermark_label    = $phraseplugin->param("TXT0003");    
}
else
{
    $plugin_watermark      	 = '$("#plugin_watermark").prop("checked", 0);';
    $plugin_watermark_label    = $phraseplugin->param("TXT0003");    
}

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
		if ( param('plugin_watermark') )
		{
		$plugin_watermark = param('plugin_watermark');
		if ($plugin_watermark eq "on" || $plugin_watermark eq "1" || $plugin_watermark eq "true") 
		{
			$plugin_watermark = 1;
		}
		else
		{
			$plugin_watermark = 0;
		}
		}
		else
		{
		$plugin_watermark = 0;
		}
		
		$plugin_cfg->param("WATERMARK", "$plugin_watermark");
		$plugin_cfg->save();
		
		print "Content-Type: text/html\n\n";
		$template_title = $phrase->param("TXT0000") . ": " . $phraseplugin->param("TXT0000");;
		$message = $phraseplugin->param("TXT0004");
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
    $cam_model_list = "$cam_model_list\n<option value=\"$cams[2]\">$cams[0] - $cams[1]</option>\n";
   # $cam_model_list = "$cam_model_list\n\"$cams[0] - $cams[1]\"\n";

}


print "Content-Type: text/html\n\n";

# Print Template
&lbheader;

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

