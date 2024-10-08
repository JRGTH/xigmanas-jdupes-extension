<?php
/*
	jdupes-gui.php

	WebGUI wrapper for the NAS4Free/XigmaNAS "Jdupes" add-on created by JoseMR, (Copyright (c) 2020).

	Copyright (c) 2016 Andreas Schmidhuber
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice, this
	   list of conditions and the following disclaimer.
	2. Redistributions in binary form must reproduce the above copyright notice,
	   this list of conditions and the following disclaimer in the documentation
	   and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
	ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
	WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
	DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
	ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
	(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
	LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
	ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
	(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
	SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

	The views and conclusions contained in the software and documentation are those
	of the authors and should not be interpreted as representing official policies,
	either expressed or implied, of the NAS4Free Project.
*/
require("auth.inc");
require("guiconfig.inc");

$application = "Jdupes";
$pgtitle = array(gtext("Extensions"), "Jdupes");

// For NAS4Free 10.x versions.
$return_val = mwexec("/bin/cat /etc/prd.version | cut -d'.' -f1 | /usr/bin/grep '10'", true);
if ($return_val == 0) {
	if (is_array($config['rc']['postinit'] ) && is_array( $config['rc']['postinit']['cmd'] ) ) {
		for ($i = 0; $i < count($config['rc']['postinit']['cmd']);) { if (preg_match('/jdupes-init/', $config['rc']['postinit']['cmd'][$i])) break; ++$i; }
	}
}

// Initialize some variables.
//$rootfolder = dirname($config['rc']['postinit']['cmd'][$i]);
$confdir = "/var/etc/jdupesconf";
$cwdir = exec("/usr/bin/grep 'INSTALL_DIR=' {$confdir}/conf/jdupes_config | cut -d'\"' -f2");
$rootfolder = $cwdir;
$configfile = "{$rootfolder}/conf/jdupes_config";
$versionfile = "{$rootfolder}/version";
//$date = strftime('%c');                // Previous PHP versions, deprecated as of PHP 8.1.
$date = date('D M d h:i:s Y', time());   // Equivalent date replacement for the previous strftime function.
$logfile = "{$rootfolder}/log/jdupes_ext.log";
$logevent = "{$rootfolder}/log/jdupes_last_event.log";
$prdname = "jdupes";


if ($rootfolder == "") $input_errors[] = gtext("Extension installed with fault");
else {
// Initialize locales.
	$textdomain = "/usr/local/share/locale";
	$textdomain_jdupes = "/usr/local/share/locale-jdupes";
	if (!is_link($textdomain_jdupes)) { mwexec("ln -s {$rootfolder}/locale-jdupes {$textdomain_jdupes}", true); }
	bindtextdomain("xigmanas", $textdomain_jdupes);
}
if (is_file("{$rootfolder}/postinit")) unlink("{$rootfolder}/postinit");

if ($_POST) {
	if(isset($_POST['upgrade']) && $_POST['upgrade']):
		$cmd = sprintf('%1$s/jdupes-init -u > %2$s',$rootfolder,$logevent);
		$return_val = 0;
		$output = [];
		exec($cmd,$output,$return_val);
		if($return_val == 0):
			ob_start();
			include("{$logevent}");
			$ausgabe = ob_get_contents();
			ob_end_clean(); 
			$savemsg .= str_replace("\n", "<br />", $ausgabe)."<br />";
		else:
			$input_errors[] = gtext('An error has occurred during upgrade process.');
			$cmd = sprintf('echo %s: %s An error has occurred during upgrade process. >> %s',$date,$application,$logfile);
			exec($cmd);
		endif;
	endif;

	// Remove only extension related files during cleanup.
	if (isset($_POST['uninstall']) && $_POST['uninstall']) {
		bindtextdomain("xigmanas", $textdomain);
		if (is_link($textdomain_jdupes)) mwexec("rm -f {$textdomain_jdupes}", true);
		if (is_dir($confdir)) mwexec("rm -Rf {$confdir}", true);
		mwexec("rm /usr/local/www/jdupes-gui.php && rm -R /usr/local/www/ext/jdupes-gui", true);
		mwexec("{$rootfolder}/jdupes-init -t", true);		
		$uninstall_cmd = "echo 'y' | jdupes-init -r";
		mwexec($uninstall_cmd, true);
		if (is_link("/usr/local/share/{$prdname}")) mwexec("rm /usr/local/share/{$prdname}", true);
		if (is_link("/var/cache/pkg")) mwexec("rm /var/cache/pkg", true);
		if (is_link("/var/db/pkg")) mwexec("rm /var/db/pkg && mkdir /var/db/pkg", true);
		
		// Remove postinit cmd in NAS4Free 10.x versions.
		$return_val = mwexec("/bin/cat /etc/prd.version | cut -d'.' -f1 | /usr/bin/grep '10'", true);
			if ($return_val == 0) {
				if (is_array($config['rc']['postinit']) && is_array($config['rc']['postinit']['cmd'])) {
					for ($i = 0; $i < count($config['rc']['postinit']['cmd']);) {
					if (preg_match('/jdupes-init/', $config['rc']['postinit']['cmd'][$i])) { unset($config['rc']['postinit']['cmd'][$i]); }
					++$i;
				}
			}
			write_config();
		}

		// Remove postinit cmd in NAS4Free later versions.
		if (is_array($config['rc']) && is_array($config['rc']['param'])) {
			$postinit_cmd = "{$rootfolder}/jdupes-init";
			$value = $postinit_cmd;
			$sphere_array = &$config['rc']['param'];
			$updateconfigfile = false;
		if (false !== ($index = array_search_ex($value, $sphere_array, 'value'))) {
			unset($sphere_array[$index]);
			$updateconfigfile = true;
		}
		if ($updateconfigfile) {
			write_config();
			$updateconfigfile = false;
		}
	}
	header("Location:index.php");
}

}

function get_version_jdupes() {
	global $tarballversion, $prdname;
	if (is_file("{$tarballversion}")) {
		exec("/bin/cat {$tarballversion}", $result);
		return ($result[0] ?? '');
	}
	else {
		exec("/usr/local/bin/{$prdname} -v | awk 'NR==1'", $result);
		return ($result[0] ?? '');
	}
}

function get_version_ext() {
	global $versionfile;
	exec("/bin/cat {$versionfile}", $result);
	return ($result[0] ?? '');
}

function get_process_pid() {
	global $pidfile;
	exec("/bin/cat {$pidfile}", $state); 
	return ($state[0]);
}

if (is_ajax()) {
	$getinfo['jdupes'] = get_version_jdupes();
	$getinfo['ext'] = get_version_ext();
	render_ajax($getinfo);
}

bindtextdomain("xigmanas", $textdomain);
include("fbegin.inc");
bindtextdomain("xigmanas", $textdomain_jdupes);
?>
<script type="text/javascript">//<![CDATA[
$(document).ready(function(){
	var gui = new GUI;
	gui.recall(0, 2000, 'jdupes-gui.php', null, function(data) {
		$('#getinfo').html(data.info);
		$('#getinfo_jdupes').html(data.jdupes);
		$('#getinfo_ext').html(data.ext);
	});
});
//]]>
</script>
<!-- The Spinner Elements -->
<script src="js/spin.min.js"></script>
<!-- use: onsubmit="spinner()" within the form tag -->
<script type="text/javascript">
<!--
}
//-->
</script>
<form action="jdupes-gui.php" method="post" name="iform" id="iform" onsubmit="spinner()">
	<table width="100%" border="0" cellpadding="0" cellspacing="0">
		<tr><td class="tabcont">
			<?php if (!empty($input_errors)) print_input_errors($input_errors);?>
			<?php if (!empty($savemsg)) print_info_box($savemsg);?>
			<table width="100%" border="0" cellpadding="6" cellspacing="0">
				<?php html_titleline(gtext("Jdupes"));?>
				<?php html_text("installation_directory", gtext("Installation directory"), sprintf(gtext("The extension is installed in %s"), $rootfolder));?>
				<tr>
					<td class="vncellt"><?=gtext("Jdupes version");?></td>
					<td class="vtable"><span name="getinfo_jdupes" id="getinfo_jdupes"><?=get_version_jdupes()?></span></td>
				</tr>
				<tr>
					<td class="vncellt"><?=gtext("Extension version");?></td>
					<td class="vtable"><span name="getinfo_ext" id="getinfo_ext"><?=get_version_ext()?></span></td>
				</tr>
			</table>
			<div id="submit">
				<input name="upgrade" type="submit" class="formbtn" title="<?=gtext("Upgrade Extension and Jdupes Packages");?>" value="<?=gtext("Upgrade");?>" />
			</div>
			<div id="remarks">
				<?php html_remark("note", gtext("Info"), sprintf(gtext("For general information visit the following link(s):")));?>
				<div id="enumeration"><ul><li><a href="https://man.freebsd.org/cgi/man.cgi?query=jdupes" target="_blank" > About Jdupes</a></li></ul></div>
			</div>
			<table width="100%" border="0" cellpadding="6" cellspacing="0">
				<?php html_separator();?>
				<?php html_titleline(gtext("Uninstall"));?>
				<?php html_separator();?>
			</table>
			<div id="submit1">
				<input name="uninstall" type="submit" class="formbtn" title="<?=gtext("Uninstall Extension and Jdupes completely");?>" value="<?=gtext("Uninstall");?>" onclick="return confirm('<?=gtext("Jdupes Extension and packages will be completely removed, ready to proceed?");?>')" />
			</div>
		</td></tr>
	</table>
	<?php include("formend.inc");?>
</form>
<script type="text/javascript">
<!--
enable_change(false);
//-->
</script>
<?php include("fend.inc");?>
