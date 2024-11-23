<?php

namespace EAD_XML_Importer;

use EAD_XML_Importer\Logger;
use Exception;
use WP_Query;

class DataLoader
{
    private $url;
    private $options;
    private $dataArray;
    private $postData;

    public function __construct($url, $options = [])
    {
        $this->url = $url;

        $this->options = array_merge([
            'namespaceSeparator' => ':',
            'attributePrefix' => '',
            'alwaysArray' => [],
            'autoArray' => true,
            'textContent' => 'value',
            'autoText' => true,
            'keySearch' => false,
            'keyReplace' => false
        ], $options);
        $this->dataArray = null;
        $this->postData = null;
    }

    /**
     * Cron task to fetch XML data from URL and update custom post type
     * 
     * @param string $url URL
     * @param string $postType Custom post type
     * @return void
     */
    public static function manageCPT($url, $postType = 'archive')
    {
        $converter = new self($url);
        $converter->convert();
        $converter->populateCustomPostType($postType);
    }

    /**
     * Convert XML data to JSON
     * 
     * @return string JSON data
     */
    public function convert($type = 'array')
    {
        $xmlData = $this->fetchXMLData();

        if ($xmlData === false) {
            error_log("Error fetching XML data from URL.");

            return false;
        }

        $xml = simplexml_load_string($xmlData);

        if ($xml === false) {
            error_log("Error parsing XML data.");

            return false;
        }

        $dataArray = $this->xmlToArray($xml);
        if ($type === 'json') {
            $this->dataArray = json_encode($dataArray, JSON_PRETTY_PRINT);
        } else if ($type === 'array') {
            $this->dataArray = $dataArray;
        } else {
            error_log("Invalid type. Must be 'json' or 'array'.");

            return false;
        }

        $this->postData = $this->getEAD();

        return $this->dataArray;
    }

    /**
     * Fetch XML data from the URL
     * 
     * @return string XML data
     */
    private function fetchXMLData(): string
    {
        return file_get_contents($this->url);
    }


    /**
     * Convert XML to an array
     * 
     * @param SimpleXMLElement $xml
     * @return array XML data
     */

    private function xmlToArray($xml)
    {
        $namespaces = $xml->getDocNamespaces();
        $namespaces[''] = null;

        $attributesArray = array();
        foreach ($namespaces as $prefix => $namespace) {
            foreach ($xml->attributes($namespace) as $attributeName => $attribute) {
                if ($this->options['keySearch']) {
                    $attributeName = str_replace($this->options['keySearch'], $this->options['keyReplace'], $attributeName);
                }
                $attributeKey = $this->options['attributePrefix']
                    . ($prefix ? $prefix . $this->options['namespaceSeparator'] : '')
                    . $attributeName;
                $attributesArray[$attributeKey] = (string)$attribute;
            }
        }

        $tagsArray = array();
        foreach ($namespaces as $prefix => $namespace) {
            foreach ($xml->children($namespace) as $childXml) {
                $childTagName = $childXml->getName();
                if ($this->options['keySearch']) {
                    $childTagName = str_replace($this->options['keySearch'], $this->options['keyReplace'], $childTagName);
                }
                $childProperties = $this->xmlToArray($childXml);
                if (isset($tagsArray[$childTagName])) {
                    if (is_array($tagsArray[$childTagName]) && isset($tagsArray[$childTagName][0])) {
                        $tagsArray[$childTagName][] = $childProperties;
                    } else {
                        $tagsArray[$childTagName] = array($tagsArray[$childTagName], $childProperties);
                    }
                } else {
                    $tagsArray[$childTagName] = $childProperties;
                }
            }
        }

        $textContentArray = array();
        $plainText = trim((string)$xml);
        if ($plainText !== '') {
            $textContentArray[$this->options['textContent']] = $plainText;
        }

        $propertiesArray = !$this->options['autoText'] || $attributesArray || $tagsArray || ($plainText === '')
            ? array_merge($attributesArray, $tagsArray, $textContentArray) : $plainText;

        if (in_array($xml->getName(), $this->options['alwaysArray']) && !is_array($propertiesArray)) {
            $propertiesArray = array($propertiesArray);
        }

        return $propertiesArray;
    }

    /**
     * Save JSON data to a file
     * 
     * @param string $jsonData JSON data
     * @param string $filePath File path
     * @return void
     */

    public function saveJsonToFile($jsonData, $filePath)
    {
        file_put_contents($filePath, $jsonData);
        echo "JSON data saved to $filePath\n";
    }

    /**
     * Pupulate custom post type with JSON data
     * 
     * @param string $postType Custom post type
     * @return void
     */
    public function populateCustomPostType($postType = 'archive', $post_id = null)
    {
        $data = $this->dataArray;

        if ($data === null) {
            throw new Exception("No data to populate custom post type.");
        }

        if ($post_id) {
            // Check if a post with the same ead_id already exists
            $existing_post = new WP_Query(array(
                'p' => $post_id,
                'post_type' => $postType,
                'posts_per_page' => 1,
                'meta_query' => array(
                    array(
                        'key' => get_option('exi_post_type_meta_url', 'ead_url'),
                        'compare' => 'EXISTS'
                    ),
                ),
            ));

            if ($existing_post->have_posts()) {
                $post_id = $existing_post->posts[0]->ID;

                $post_id = wp_update_post(array(
                    'ID' => $post_id,
                    'post_slug' => $this->getEADslug(),
                    'post_title' => $this->getEADtitle(),
                    'post_type' => $postType,
                    'post_status' => 'publish',
                ));

                $this->updatePostMeta($post_id);
            }
        } else {
            $post_id = wp_insert_post(array(
                'post_title' => $this->getEADtitle(),
                'post_slug' => $this->getEADslug(),
                'post_type' => $postType,
                'post_status' => 'publish',
            ));

            if ($post_id === 0) {
                throw new Exception("Error creating custom post type.");
            }

            $this->updatePostMeta($post_id, true);
        }
    }

    /**
     * Update post meta data
     * 
     * @param int $post_id Post ID
     * @param bool $new Is this a new post?
     * @return void
     */
    public function updatePostMeta($post_id, $new = false)
    {
        // Basic fields
        update_post_meta($post_id, 'ead_id', $this->getEADID());
        update_post_meta($post_id, 'ead_url', $this->getEADurl() ?? get_post_meta($post_id, get_option('exi_post_type_meta_url', 'ead_url'), true));
        update_post_meta($post_id, 'ead_publisher', $this->getEADpublisher());
        update_post_meta($post_id, 'ead_publication', $this->getEADpublication());
        update_post_meta($post_id, 'ead_creation', $this->getEADcreation());
        update_post_meta($post_id, 'ead_creation_date', $this->getEADcreationDate());
        update_post_meta($post_id, 'ead_corpname', $this->getCorpname());
        update_post_meta($post_id, 'ead_note', $this->getNote());
        update_post_meta($post_id, 'ead_scopecontent', $this->getScopecontent());

        // Check if ACF functions exist
        $use_acf = function_exists('add_row') && function_exists('delete_row');

        // For langusage
        $langusage = $this->prepareRepeater($this->getEADlangusage());
        if (!empty($langusage)) {
            if ($use_acf) {
                // delete all rows
                for ($i = 1; $i <= count(get_field('ead_langusage', $post_id)); $i++) {
                    delete_row('ead_langusage', $i, $post_id);
                }
                foreach ($langusage as $row) {
                    add_row('ead_langusage', $row, $post_id);
                }
            } else {
                delete_post_meta($post_id, 'ead_langusage');
                update_post_meta($post_id, 'ead_langusage', $langusage);
            }
        }

        // For langmaterial
        $langmaterial = $this->prepareRepeater($this->getLangmaterial());
        if (!empty($langmaterial)) {
            if ($use_acf) {
                // delete all rows
                for ($i = 1; $i <= count(get_field('ead_langmaterial', $post_id)); $i++) {
                    delete_row('ead_langmaterial', $i, $post_id);
                }
                foreach ($langmaterial as $row) {
                    add_row('ead_langmaterial', $row, $post_id);
                }
            } else {
                delete_post_meta($post_id, 'ead_langmaterial');
                update_post_meta($post_id, 'ead_langmaterial', $langmaterial);
            }
        }

        // For periode (unitdate)
        $periode = $this->prepareRepeater($this->getUnitdate());
        if (!empty($periode)) {
            if ($use_acf) {
                // delete all rows
                for ($i = 1; $i <= count(get_field('ead_periode', $post_id)); $i++) {
                    delete_row('ead_periode', $i, $post_id);
                }
                foreach ($periode as $row) {
                    add_row('ead_periode', $row, $post_id);
                }
            } else {
                delete_post_meta($post_id, 'ead_periode');
                update_post_meta($post_id, 'ead_periode', $periode);
            }
        }

        // For physdesc
        $physdesc = $this->getPhysdesc();
        if (!empty($physdesc)) {
            $physdesc_data = [
                [
                    'key' => 'phys',
                    'value' => $physdesc['phys']
                ],
                [
                    'key' => 'desc',
                    'value' => $physdesc['desc']
                ]
            ];

            if ($use_acf) {
                // delete all rows
                for ($i = 1; $i <= count(get_field('ead_physdesc', $post_id)); $i++) {
                    delete_row('ead_physdesc', $i, $post_id);
                }
                foreach ($physdesc_data as $row) {
                    add_row('ead_physdesc', $row, $post_id);
                }
            } else {
                delete_post_meta($post_id, 'ead_physdesc');
                update_post_meta($post_id, 'ead_physdesc', $physdesc_data);
            }
        }
    }

    /**
     * Prepare data for ACF repeater field
     * 
     * @param array|mixed $values Values to prepare
     * @return array Formatted data for ACF repeater
     */
    public function prepareRepeater($values): array
    {
        // If values is not an array or is empty, return empty array
        if (!is_array($values) || empty($values)) {
            return [];
        }

        $repeaterData = [];

        // Check if it's a simple array or key-value pair array
        $isSequential = array_keys($values) === range(0, count($values) - 1);

        if ($isSequential) {
            // Handle sequential array (e.g., [1, 2, 3])
            foreach ($values as $value) {
                $repeaterData[] = [
                    'key' => '',
                    'value' => $value
                ];
            }
        } else {
            // Handle associative array (e.g., ['key1' => 'value1', 'key2' => 'value2'])
            foreach ($values as $key => $value) {
                $repeaterData[] = [
                    'key' => $key,
                    'value' => $value
                ];
            }
        }

        return $repeaterData;
    }

    /**
     * Get EAD data
     */
    public function getEADHeader()
    {
        return $this->dataArray['eadheader'];
    }

    /**
     * Get EAD ID
     * 
     * @return string EAD ID
     */
    public function getEADID(): string
    {
        $id = $this->getEADHeader()['eadid']['mainagencycode'] ?? '';
        $value = $id . ' ' . $this->getEADHeader()['eadid']['value'] ?? '';

        return $value ?? '';
    }

    /**
     * Get EAD slug
     * 
     * @return string slug
     */
    public function getEADslug(): string
    {
        return $this->getEADHeader()['eadid']['identifier'] ?? '';
    }

    /**
     * Get EAD URL
     * 
     * @return string URL
     */
    public function getEADurl(): string
    {
        return $this->getEADHeader()['eadid']['url'] ?? '';
    }

    /**
     * Get title
     * 
     * @return string title
     */
    public function getEADtitle(): string
    {
        return $this->getEADHeader()['filedesc']['titlestmt']['titleproper']['value'] ?? '';
    }

    /**
     * Get publisher
     * 
     * @return string publisher
     */
    public function getEADpublisher(): string
    {
        if (isset($this->getEADHeader()['filedesc']['publicationstmt']['publisher']['value'])) {
            return $this->getEADHeader()['filedesc']['publicationstmt']['publisher']['value'];
        } else if (isset($this->getEADHeader()['filedesc']['publicationstmt']['publisher'])) {
            return $this->getEADHeader()['filedesc']['publicationstmt']['publisher'];
        }
        return '';
    }

    /**
     * Get publication date
     * 
     * @return string publication date
     */
    public function getEADpublication(): string
    {
        if (isset($this->getEADHeader()['filedesc']['publicationstmt']['date']['value'])) {
            return $this->getEADHeader()['filedesc']['publicationstmt']['date']['value'];
        } else if (isset($this->getEADHeader()['filedesc']['publicationstmt']['date'])) {
            return $this->getEADHeader()['filedesc']['publicationstmt']['date'];
        }
        return '';
    }

    /**
     * Get creation platform
     * 
     * @return string creation
     */
    public function getEADcreation(): string
    {
        return $this->getEADHeader()['profiledesc']['creation']['value'] ?? '';
    }

    /**
     * Get creation date
     * 
     * @return string creation date
     */
    public function getEADcreationDate(): string
    {
        return $this->getEADHeader()['profiledesc']['creation']['date']['value'] ?? '';
    }

    /**
     * Get langusage
     * 
     * @return array langusage
     */
    public function getEADlangusage(): array
    {
        if (!isset($this->getEADHeader()['profiledesc']['langusage'])) {
            return [];
        }

        $langs = [];
        foreach ($this->getEADHeader()['profiledesc']['langusage'] as $lang) {
            if (isset($lang['langcode']) && isset($lang['value'])) {
                $langs[$lang['langcode']] = $lang['value'];
            }
        }

        return $langs ?? [];
    }

    /**
     * Get archdesc
     * 
     * @return array archdesc
     */
    public function getArchdesc(): array
    {
        return $this->dataArray['archdesc'] ?? [];
    }

    /**
     * Get physdesc
     * 
     * @return array physdesc
     */
    public function getPhysdesc(): array
    {
        // convert 0,0 string to 0.0 and convert to float
        $data = null;

        if (isset($this->getArchdesc()['did']['physdesc']['value'])) {
            $data = $this->getArchdesc()['did']['physdesc']['value'];
        } else if (isset($this->getArchdesc()['did']['physdesc'])) {
            $data = $this->getArchdesc()['did']['physdesc'];
        } else {
            return [
                'phys' => 0.0,
                'desc' => '',
            ];
        }

        if (strpos($data, ',') !== false) {
            $data = str_replace(',', '.', $data);
        }

        $splited = explode(' ', $data);

        // remove non-numeric characters
        $numeric = preg_replace('/[^0-9.x]/', '', $splited[0]) ?? '';

        // remove $numeric from $data et trim
        $string = trim(str_replace($numeric, '', $data)) ?? '';

        return [
            'phys' => $numeric ?? 0.0,
            'desc' => $string ?? '',
        ];
    }

    /**
     * Get unitdate
     * 
     * @return array unitdate
     */
    public function getUnitdate(): array
    {
        if (isset($this->getArchdesc()['did']['unitdate']['normal'])) {
            if (strpos($this->getArchdesc()['did']['unitdate']['normal'], '/') !== false) {
                return explode('/', $this->getArchdesc()['did']['unitdate']['normal']) ?? [];
            } else if (strpos($this->getArchdesc()['did']['unitdate']['normal'], '-') !== false) {
                return explode('-', $this->getArchdesc()['did']['unitdate']['normal']) ?? [];
            }
        } else if (!is_array($this->getArchdesc()['did']['unitdate'])) {
            if (strpos($this->getArchdesc()['did']['unitdate'], '/') !== false) {
                return explode('/', $this->getArchdesc()['did']['unitdate']) ?? [];
            } else if (strpos($this->getArchdesc()['did']['unitdate'], '-') !== false) {
                return explode('-', $this->getArchdesc()['did']['unitdate']) ?? [];
            }
        }

        return [];
    }

    /**
     * Get corpname
     * 
     * @return string corpname
     */
    public function getCorpname(): string
    {
        if (isset($this->getArchdesc()['did']['repository']['corpname'])) {
            return $this->getArchdesc()['did']['repository']['corpname'] ?? '';
        } else if (isset($this->getArchdesc()['did']['origination']['name'])) {
            return $this->getArchdesc()['did']['origination']['name']['value'] ?? '';
        }

        return '';
    }

    /**
     * Get langmaterial
     * 
     * @return array langmaterial
     */

    public function getLangmaterial(): array
    {
        // Check if 'did' and 'langmaterial' exist in archdesc
        $archDesc = $this->getArchdesc();
        if (!isset($archDesc['did']) || !isset($archDesc['did']['langmaterial'])) {
            return [];
        }

        $langmaterial = $archDesc['did']['langmaterial'];

        // If langmaterial is not an array, convert it to array with single value
        if (!is_array($langmaterial)) {
            return empty($langmaterial) ? [] : [$langmaterial];
        }

        $langs = [];
        foreach ($langmaterial as $lang) {
            if (isset($lang['langcode']) && isset($lang['value'])) {
                $langs[$lang['langcode']] = $lang['value'];
            }
        }

        return $langs;
    }

    /**
     * Get note
     * 
     * @return string note
     */
    public function getNote(): string
    {
        return $this->getArchdesc()['did']['note']['p'] ?? '';
    }

    /**
     * Get scopecontent
     * 
     * @return string scopecontent
     */
    public function getScopecontent(): string
    {
        $scopecontent = $this->getArchdesc()['scopecontent']['p'] ?? '';

        // Handle array of paragraphs
        if (is_array($scopecontent)) {
            // If it's a simple array, join with newlines
            if (isset($scopecontent[0]) && !is_array($scopecontent[0])) {
                return implode("\n\n", $scopecontent);
            }
            // If we have a nested structure, try to get the 'value' key
            return implode("\n\n", array_map(function ($p) {
                return is_array($p) ? ($p['value'] ?? '') : $p;
            }, $scopecontent));
        }

        // If it's already a string, return it
        return (string)$scopecontent;
    }

    /**
     * Get EAD data as JSON formated array
     * 
     * @return array EAD data
     */

    public function getEAD()
    {
        return [
            'title' => $this->getEADtitle(),
            'slug' => sanitize_title($this->getEADtitle()),
            'id' => $this->getEADID(),
            'url' => $this->getEADurl(),
            'publisher' => $this->getEADpublisher(),
            'publication' => $this->getEADpublication(),
            'creation' => $this->getEADcreation(),
            'creation_date' => $this->getEADcreationDate(),
            'langusage' => $this->getEADlangusage(),
            'langmaterial' => $this->getLangmaterial(),
            'periode' => $this->getUnitdate(),
            'physdesc' => $this->getPhysdesc(),
            'corpname' => $this->getCorpname(),
            'note' => $this->getNote(),
            'scopecontent' => $this->getScopecontent(),
        ];
    }

    /**
     * Display EAD data in an HTML table for preview
     * 
     * @return string HTML table
     */

    public function htmlTable()
    {
        echo '<style>
             .styled-table {
                 border-collapse: collapse;
                 margin: 25px 0;
                 font-size: 0.9em;
                 font-family: \'Arial\', sans-serif;
                 min-width: 400px;
                 box-shadow: 0 0 20px rgba(0, 0, 0, 0.15);
             }
             .styled-table thead tr {
                 background-color: red;
                 color: #ffffff;
                 text-align: left;
             }
             .styled-table th,
             .styled-table td {
                 padding: 12px 15px;
                 border: 1px solid #ddd;
             }
             .styled-table tbody tr {
                 border-bottom: 1px solid #dddddd;
             }
             .styled-table tbody tr:nth-of-type(even) {
                 background-color: #f3f3f3;
             }
             .styled-table tbody tr:last-of-type {
                 border-bottom: 2px solid red;
             }
             .styled-table tbody tr.active-row {
                 font-weight: bold;
                 color: red;
             }
             .nested-table {
                 width: 100%;
                 border-collapse: collapse;
             }
             .nested-table th,
             .nested-table td {
                 padding: 8px;
                 border: 1px solid #ddd;
             }
             .nested-table tbody tr:nth-of-type(even) {
                 background-color: #f9f9f9;
             }
         </style>';

        echo '<table class="styled-table">';
        echo '<thead><tr><th>Key</th><th>Value</th></tr></thead>';
        echo '<tbody>';
        foreach ($this->getEAD() as $key => $value) {
            if (is_array($value)) {
                echo "<tr><td>$key</td><td>";
                echo '<table class="nested-table">';
                echo '<thead><tr><th>Sub-Key</th><th>Sub-Value</th></tr></thead>';
                echo '<tbody>';
                foreach ($value as $k => $v) {
                    echo "<tr><td>$k</td><td>$v</td></tr>";
                }
                echo '</tbody>';
                echo '</table>';
                echo '</td></tr>';
            } else {
                echo "<tr><td>$key</td><td>$value</td></tr>";
            }
        }
        echo '</tbody>';
        echo '</table>';
    }
}
