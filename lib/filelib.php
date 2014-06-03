<?php
/***************************************************************************
* filelib.php - File Library
* -------------------------------------------------------------------------
* Author: Matthew Davidson
* Date: 2/3/2012
* Revision: 0.0.5
***************************************************************************/
 
if(!isset($LIBHEADER)){ include('header.php'); }
$FILELIB = true;

function delete_old_files($path, $days = 1){
global $CFG;
	$seconds = $days * (24*60*60);
	$dir    = $CFG->dirroot . $path;
	$files = scandir($dir);
	foreach ($files as $num => $fname){
		if (file_exists("{$dir}{$fname}") && ((time() - filemtime("{$dir}{$fname}")) > $seconds)) {
			$mod_time = filemtime("{$dir}{$fname}");
			if($fname != ".."){	
				if (unlink("{$dir}{$fname}")){$del = $del + 1;}
			}
		}
	}
}

function delete_file($filepath){
    if (file_exists($filepath)){
		unlink($filepath);
	}
}

function recursive_mkdir( $folder ){
    $folder = preg_split( "/[\\\\\/]/" , $folder );
    $mkfolder = '';
    for(  $i=0 ; isset( $folder[$i] ) ; $i++ ){
        if(!strlen(trim($folder[$i])))continue;
        $mkfolder .= $folder[$i];
        if( !is_dir( $mkfolder ) ){
          mkdir( "$mkfolder" ,  0777);
          chmod("$mkfolder", 0777);
        }
        $mkfolder .= DIRECTORY_SEPARATOR;
    }
}

function recursive_delete ( $folderPath ){
    if ( is_dir ( $folderPath ) ){
        foreach ( scandir ( $folderPath )  as $value ){
            if ( $value != "." && $value != ".." ){
                $value = $folderPath . "/" . $value;
                if ( is_dir ( $value ) ){
                    FolderDelete ( $value );
                }elseif ( is_file ( $value ) ){
                    @unlink ( $value );
                }
            }
        }
        return rmdir ( $folderPath );
    }else{
        return false;
    }
}

function copy_file($old,$new){
    if (file_exists($old)){
		copy($old, $new) or die("Unable to copy $old to $new.");
	}
}

function make_csv($filename,$contents){
    $tempdir = sys_get_temp_dir() == "" ? "/tmp/" : sys_get_temp_dir();
    $tmpfname = tempnam($tempdir, $filename);
    if(file_exists($tmpfname)){	unlink($tmpfname); }
    $handle = fopen($tmpfname, "w");
    foreach($contents as $fields){
        fputcsv($handle, $fields);
    }  
    fclose($handle); 
    rename($tmpfname,$tempdir."/".$filename);  
    return addslashes($tempdir."/".$filename);       
}

function create_file($filename,$contents,$makecsv=false){
    if($makecsv){
        return make_csv($filename,$contents);
    }else{
        $tempdir = sys_get_temp_dir() == "" ? "/tmp/" : sys_get_temp_dir();
        $tmpfname = tempnam($tempdir, $filename);
        if(file_exists($tmpfname)){	unlink($tmpfname); }
        $handle = fopen($tmpfname, "w");
        
        fwrite($handle, stripslashes($contents));
        fclose($handle);
        rename($tmpfname,$tempdir."/".$filename);  
        return addslashes($tempdir."/".$filename);        
    }
}

function get_download_link($filename,$contents,$makecsv=false){
    global $CFG;
    return 'window.open("'.$CFG->wwwroot . '/scripts/download.php?file='.create_file($filename,$contents,$makecsv).'", "download","menubar=yes,toolbar=yes,scrollbars=1,resizable=1,width=600,height=400");';
}


function return_bytes ($size_str){
    switch (substr ($size_str, -1)){
        case 'M': case 'm': case 'mb': return (int)$size_str * 1048576;
        case 'K': case 'k': case 'kb': return (int)$size_str * 1024;
        case 'G': case 'g': case 'gb': return (int)$size_str * 1073741824;
        default: return $size_str;
    }
}
?>