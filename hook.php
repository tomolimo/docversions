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


if (!function_exists('arTableExists')) {
   function arTableExists($table) {
      global $DB;
      if (method_exists( $DB, 'tableExists')) {
         return $DB->tableExists($table);
      } else {
         return TableExists($table);
      }
   }
}


if (!function_exists('arFieldExists')) {
   function arFieldExists($table, $field, $usecache = true) {
      global $DB;
      if (method_exists( $DB, 'fieldExists')) {
         return $DB->fieldExists($table, $field, $usecache);
      } else {
         return FieldExists($table, $field, $usecache);
      }
   }
}


/**
 * Summary of plugin_docversions_install
 * @return true or die!
 */
function plugin_docversions_install() {
    global $DB;

   if (!arTableExists("glpi_plugin_docversions_configs")) {
      $query = "  CREATE TABLE `glpi_plugin_docversions_configs` (
	                    `id` INT(11) NOT NULL AUTO_INCREMENT,
	                    PRIMARY KEY (`id`)
                    )
                    COLLATE='utf8_general_ci'
                    ENGINE=InnoDB
                    ;
			";

      $DB->query($query) or die("error creating glpi_plugin_docversions_configs" . $DB->error());

      // add configuration singleton
      $query = "INSERT INTO `glpi_plugin_docversions_configs` (`id`) VALUES (1);";
      $DB->query( $query ) or die("error creating default record in glpi_plugin_docversions_configs" . $DB->error());

   }

   if (!arTableExists("glpi_plugin_docversions_versions")) {
      $query = "  CREATE TABLE `glpi_plugin_docversions_versions` (
	                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                       `date_creation` TIMESTAMP NULL DEFAULT NULL,
                       `documents_id` INT(11) NOT NULL,
                       `version` INT(11) NOT NULL,
                       `filename` VARCHAR(255) NULL DEFAULT NULL COMMENT 'for display and transfert' COLLATE 'utf8_unicode_ci',
                       `filepath` VARCHAR(255) NULL DEFAULT NULL COMMENT 'file storage path' COLLATE 'utf8_unicode_ci',
                       `is_deleted` TINYINT(1) NOT NULL DEFAULT '0',
                       `users_id` INT(11) NOT NULL DEFAULT '0',
                       `sha1sum` CHAR(40) NULL DEFAULT NULL COLLATE 'utf8_unicode_ci',
	                    PRIMARY KEY (`id`),
                       INDEX `date_creation` (`date_creation`),
                       INDEX `sha1sum` (`sha1sum`),
	                    UNIQUE INDEX `documents_id_version` (`documents_id`, `version`),
	                    INDEX `documents_id` (`documents_id`),
	                    INDEX `version` (`version`)
                    )
                    COLLATE='utf8_general_ci'
                    ENGINE=InnoDB
                    ;
			";

      $DB->query($query) or die("error creating glpi_plugin_docversions_versions" . $DB->error());
   }

   if (!arTableExists("glpi_plugin_docversions_documents")) {
      $query = "  CREATE TABLE `glpi_plugin_docversions_documents` (
	                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                       `documents_id` INT(11) NOT NULL,
                       `version` INT(11) NOT NULL DEFAULT 1,
	                    PRIMARY KEY (`id`),
	                    UNIQUE INDEX `documents_id` (`documents_id`)
                    )
                    COLLATE='utf8_general_ci'
                    ENGINE=InnoDB
                    ;
			";

      $DB->query($query) or die("error creating glpi_plugin_docversions_versions" . $DB->error());
   }

   // will create plugin folder in GLPI_PLUGIN_DOC_DIR
   if (!is_dir(GLPI_PLUGIN_DOC_DIR."/docversions")) {
      mkdir(GLPI_PLUGIN_DOC_DIR."/docversions");
   }
   if (!is_dir(DOCVERSIONS_DOC_DIR)) {
      mkdir(DOCVERSIONS_DOC_DIR);
   }
   if (!is_dir(DOCVERSIONS_TMP_DIR)) {
      mkdir(DOCVERSIONS_TMP_DIR);
   }

   return true;
}


function plugin_docversions_uninstall() {
    global $DB;

    return true;
}

/**
 * Summary of docversionsHook
 */
class DocversionsHook {
   public static function pre_item_update_docversions($item) {
      if (isset($item->input['_filename']) && isset($item->input['current_filepath'])) {
         // a new file has been saved
         // backup current file into _tmp folder
         $path = explode('/', $item->input['current_filepath']);
         copy(GLPI_DOC_DIR."/".$item->input['current_filepath'], DOCVERSIONS_TMP_DIR."/".$path[1].$path[2]);
      }
   }

   public static function item_update_docversions($item) {
      if (isset($item->oldvalues['filepath'])) {
         // restore old file from _tmp folder
         $path = explode('/', $item->oldvalues['filepath']);
         @mkdir(DOCVERSIONS_DOC_DIR."/".$path[0]."/".$path[1], 0777, true);
         rename(DOCVERSIONS_TMP_DIR."/".$path[1].$path[2], DOCVERSIONS_DOC_DIR."/".$item->oldvalues['filepath']);

         $filename = isset($item->oldvalues['filename']) ? $item->oldvalues['filename'] : $item->fields['filename'];

         // get version number for document
         $docdocument = new PluginDocversionsDocument;
         $version = ['version' => 1, 'documents_id' => $item->getID()];
         $docvs = getAllDatasFromTable($docdocument->getTable(), "`documents_id` = ".$version['documents_id']);
         if (count($docvs) > 0) {
            $version = array_pop($docvs);
         }

         // add a new version to this former document
         $docversion = new PluginDocversionsVersion;
         $docversion->add([ 'documents_id' => $version['documents_id'],
                            'version' => $version['version'],
                            'filename' => $filename,
                            'filepath' => $item->oldvalues['filepath'],
                            'is_deleted' => 0,
                            'users_id' => Session::getLoginUserID(),
                            'sha1sum' => $item->oldvalues['sha1sum']
                           ]);

         $version['version'] += 1;

         // add or update version for current document
         if (isset($version['id'])) {
            // record already exists, then update it
            $docdocument->update($version);
         } else {
            // add a new record
            $docdocument->add($version);
         }
      }
   }

}
