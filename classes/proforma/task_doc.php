<?php

use core\context\user;
use core\exception\invalid_state_exception;

define('PROFORMA_TASK_XML_NAMESPACES', [/* First namespace is default namespace. */'urn:proforma:v2.1']);

/**
 * Represents a task.xml file and allows extracting all necesarry information for
 * a VPL activity
 */
class proforma_task_doc {
    private DOMDocument $doc;
    private string $namespace;

    public function __construct(string $filecontent) {
        $this->doc = new DOMDocument();
        $this->doc->loadXML($filecontent);
        $this->namespace = $this->find_proforma_namespace($this->doc);
    }

    /**
     * Extracts the task title from the task document
     */
    public function get_task_title(): string {
        $titleElement = $this->doc->getElementsByTagNameNS($this->namespace, 'title')[0];
        return $titleElement->nodeValue;
    }

    /**
     * Extracts the task description from the task document
     */
    public function get_task_description(): string {
        $descriptionElement = $this->doc->getElementsByTagNameNS($this->namespace, 'description')[0];
        return $descriptionElement->nodeValue;
    }

    /**
     * Extracts all files that have the visible-attribute set to "yes"
     * Returns an array with the fields 'name' and 'content', with each
     * entry representing a file
     */
    public function get_visible_files(int $draftitemid): array {
        global $USER;
        $fs = get_file_storage();
        $usercontext = user::instance($USER->id);

        $files = array();

        $filesElement = $this->doc->getElementsByTagNameNS($this->namespace, 'files')[0];
        foreach ($filesElement->childNodes as $fileElement) {
            if ($fileElement->nodeType === XML_ELEMENT_NODE) {
                $visible = $fileElement->getAttribute('visible') === 'yes';
                if ($visible) {
                    $attachedBinFiles = $fileElement->getElementsByTagNameNS($this->namespace, 'attached-bin-file');
                    $attachedTxtFiles = $fileElement->getElementsByTagNameNS($this->namespace, 'attached-txt-file');
                    $embeddedBinFiles = $fileElement->getElementsByTagNameNS($this->namespace, 'embedded-bin-file');
                    $embeddedTxtFiles = $fileElement->getElementsByTagNameNS($this->namespace, 'embedded-txt-file');
                    if ($attachedBinFiles->length > 0 || $attachedTxtFiles->length > 0) {
                        $attachedFileValue = '';
                        if ($attachedBinFiles->length > 0) {
                            $attachedFileValue = $attachedBinFiles[0]->nodeValue;
                        } elseif ($attachedTxtFiles->length > 0) {
                            $attachedFileValue = $attachedTxtFiles[0]->nodeValue;
                        }
                        $pathInfo = pathinfo($attachedFileValue);
                        $file = $fs->get_file($usercontext->id, 'user', 'draft', $draftitemid, $pathInfo['dirname'] . '/', $pathInfo['basename']);
                        if (!$file) {
                            throw new invalid_state_exception('File with name ' . $attachedFileValue . ' not found');
                        }
                        $files[] = [
                            'name' => $attachedFileValue,
                            'content' => $file->get_content()
                        ];
                    } elseif ($embeddedBinFiles->length > 0 || $embeddedTxtFiles->length > 0) {
                        $embeddedFileName = '';
                        $embeddedFileContent = '';
                        if ($embeddedBinFiles->length > 0 ) {
                            $embeddedFileName = $embeddedBinFiles[0]->getAttribute('filename');
                            $embeddedFileContent = $embeddedBinFiles[0]->nodeValue;
                        } elseif ($embeddedTxtFiles->length > 0) {
                            $embeddedFileName = $embeddedTxtFiles[0]->getAttribute('filename');
                            $embeddedFileContent = $embeddedTxtFiles[0]->nodeValue;
                        }
                        $files[] = [
                            'name' => $embeddedFileName,
                            'content' => $embeddedFileContent
                        ];
                    }
                }
            }
        }
        return $files;
    }

    /**
     * Gets the ProFormA-namespace from the task document
     */
    private function find_proforma_namespace(\DOMDocument $doc): string {
        foreach (PROFORMA_TASK_XML_NAMESPACES as $namespace) {
            if ($doc->getElementsByTagNameNS($namespace, 'task')->length != 0) {
                return $namespace;
            }
        }
        return '';
    }
}