<?php
/*
 * -------------------------------------------------------------------------
Document Versions plugin
Copyright (C) 2018 by Raynet SAS a company of A.Raymond Network.

http://www.araymond.com
-------------------------------------------------------------------------

LICENSE

This file is part of Document Versions plugin for GLPI.

This file is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

GLPI is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with GLPI. If not, see <http://www.gnu.org/licenses/>.
--------------------------------------------------------------------------
 */


// ----------------------------------------------------------------------
// Original Author of file: Olivier Moron
// ----------------------------------------------------------------------

function plugin_init_docversions() {
   global $PLUGIN_HOOKS,$LANG,$CFG_GLPI;

   $PLUGIN_HOOKS['csrf_compliant']['docversions'] = true;

   Plugin::registerClass('PluginDocversionsVersion', array('addtabon' => array('Document')));

   $PLUGIN_HOOKS['pre_item_update']['docversions'] = ['Document' => ['DocversionsHook', 'pre_item_update_docversions']];
   $PLUGIN_HOOKS['item_update']['docversions'] = ['Document' => ['DocversionsHook', 'item_update_docversions']];

   // Path for plugin document storage
   if (!defined("DOCVERSIONS_DOC_DIR")) {
      define("DOCVERSIONS_DOC_DIR", GLPI_PLUGIN_DOC_DIR."/docversions/files");
   }

   if (!defined("DOCVERSIONS_TMP_DIR")) {
      define("DOCVERSIONS_TMP_DIR", DOCVERSIONS_DOC_DIR."/_tmp");
   }

   // Add specific files to add to the header : javascript or css
   //$PLUGIN_HOOKS['add_javascript']['docversions'] = array('js/docversions.js');
}


// Get the name and the version of the plugin
function plugin_version_docversions() {

   return array('name'           => 'Document Versions',
                'version'        => '0.1.0',
                'author'         => 'Olivier Moron',
                'license'        => 'GPLv2+',
                'homepage'       => 'https://forge.glpi-project.org/projects/docversions/',
                'minGlpiVersion' => '9.1');
}


// Optional : check prerequisites before install : may print errors or add to message after redirect
function plugin_docversions_check_prerequisites() {

   if (version_compare(GLPI_VERSION,'9.1','lt') ) {
      echo "This plugin requires GLPI >= 9.1";
      return false;
   }
   return true;
}


// Check configuration process for plugin : need to return true if succeeded
// Can display a message only if failure and $verbose is true
function plugin_docversions_check_config($verbose=false) {

   return true;
}
