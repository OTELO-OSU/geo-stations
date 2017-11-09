<?php
namespace petrophysics\backend\controller;

class RequestController
{

    function ConfigFile()
    {
        $config = parse_ini_file($_SERVER['DOCUMENT_ROOT'] . '/Backend/config.ini');
        return $config;
    }
    /**
     *  Methode d'execution des Requetes CURL
     *
     *  @param $url :
     *          Url a appeler
     *  @param $curlopt :
     *            Option a ajouter
     *     @return $rawData:
     *            Données Json recu
     */
    function Curlrequest($url, $curlopt)
    {
        $ch = curl_init();
        $curlopt = array(
            CURLOPT_URL => $url
        ) + $curlopt;
        curl_setopt_array($ch, $curlopt);
        $rawData = curl_exec($ch);
        curl_close($ch);
        return $rawData;
    }

    /**
     *  Methode de requetes vers elasticsearch
     *
     *
     */
    function Request_all_poi()
    {

        $config = self::ConfigFile();
        $url = $config['ESHOST'] . '/' . $config['INDEX_NAME'] . "/_search?type=" . $config['COLLECTION_NAME'] ."&size=10000";
       $postcontent = '{ "_source": { 
            "includes": [ "INTRO.SAMPLING_DATE","INTRO.TITLE","INTRO.SUPPLEMENTARY_FIELDS.LITHOLOGY","INTRO.SUPPLEMENTARY_FIELDS.DESCRIPTION","INTRO.SUPPLEMENTARY_FIELDS.SAMPLE_NAME","INTRO.SUPPLEMENTARY_FIELDS.ALTERATION_DEGREE","INTRO.SUPPLEMENTARY_FIELDS.NAME_REFERENT","INTRO.SUPPLEMENTARY_FIELDS.FIRST_NAME_REFERENT",
            "INTRO.SAMPLING_DATE","INTRO.SAMPLING_POINT","INTRO.MEASUREMENT","DATA.FILES","INTRO.SAMPLE_KIND" ] 
             }}';
        $curlopt = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_PORT => $config['ESPORT'],
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $postcontent
        );
        $response = self::Curlrequest($url, $curlopt);
        $response = json_decode($response, true);
        $response = $response['hits']['hits'];
        
        $responses = array();
        $return = array();
        foreach ($response as $key => $value)
        {
            
            foreach ($value['_source']['INTRO']['SAMPLING_POINT'] as $key => $value2) {
                
            $current = $value2['ABBREVIATION'];
            if (!$return[$value2['ABBREVIATION']])
            {
                $return[$value2['ABBREVIATION']]['SAMPLING_DATE'] = $value['_source']['INTRO']['SAMPLING_DATE'][0];
             
                $return[$value2['ABBREVIATION']]['STATION'] = $value2['ABBREVIATION'];
                $return[$value2['ABBREVIATION']]['TITLE'] = $value['_source']['INTRO']['TITLE'];
                $return[$value2['ABBREVIATION']]['SAMPLE_KIND'] = $value['_source']['INTRO']['SAMPLE_KIND'][0]['NAME'];
                $return[$value2['ABBREVIATION']]['SAMPLING_POINT'][0]['LATITUDE'] = $value2['LATITUDE'];
                $return[$value2['ABBREVIATION']]['SAMPLING_POINT'][0]['DESCRIPTION'] = $value2['DESCRIPTION'];
                $return[$value2['ABBREVIATION']]['SAMPLING_POINT'][0]['LONGITUDE'] = $value2['LONGITUDE'];
                $return[$value2['ABBREVIATION']]['SAMPLING_POINT'][0]['SYSTEM'] = $value2['COORDINATE_SYSTEM'];

                foreach ($value['_source']['DATA']['FILES'] as $key => $file)
                {
                    if (exif_imagetype($file['ORIGINAL_DATA_URL']))
                    {
                        $return[$current]['PICTURES'][$key] = $file;
                    }
                }
            }

            $return[$value2['ABBREVIATION']]['MEASUREMENT'] = $value['_source']['INTRO']['MEASUREMENT'];
            }

        }
        $responses = $return;
        $responses = json_encode($responses);
        return $responses;
    }

    /**
     *  Methode de requetes vers elasticsearch
     *
     *
     */
    function Request_data_with_sort($sort)
    {
        $lithology = '';
        $mesure = '';
        $sort = json_decode($sort, true);
        if ($sort['lithology'])
        {
           $lithology = 'INTRO.SAMPLE_KIND.NAME:"' . urlencode($sort['lithology']) . '"%20AND%20';
        }
        if ($sort['mindate'] and $sort['maxdate'])
        {
            $date = 'INTRO.SAMPLING_DATE:[' . $sort['mindate'] . '%20TO%20' . $sort['maxdate'] . ']%20AND%20';
        }
        if ($sort['mesure'])
        {
            $mesure = 'INTRO.MEASUREMENT.ABBREVIATION:"' . urlencode($sort['mesure']) . '"%20AND%20';
        }
        if ($sort['lat'] and $sort['lng'])
        {
            $geo = 'INTRO.SAMPLING_POINT.LONGITUDE:[' . $sort['lat']['lat1'] . '%20TO%20' . $sort['lat']['lat2'] . ']%20AND%20INTRO.SAMPLING_POINT.LATITUDE:[' . $sort['lat']['lat1'] . '%20TO%20' . $sort['lat']['lat1'] . ']';
        }

        $config = self::ConfigFile();
        $url = $config['ESHOST'] . '/' . $config['INDEX_NAME'] . "/_search?q=" . $lithology . $mesure . $date . $geo . "type=" . $config['COLLECTION_NAME'] ."&size=10000";

        $postcontent = '{ "_source": { 
            "includes": [ "DATA","INTRO.MEASUREMENT.ABBREVIATION" ] 
             }}';
        $curlopt = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_PORT => $config['ESPORT'],
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $postcontent
        );
        $response = self::Curlrequest($url, $curlopt);
        $response = json_decode($response, true);
        $response = $response['hits']['hits'];
        $responses = array();
        $finalcsv = array();
        $finalcsvuniq = array();
        echo '<link rel="stylesheet" type="text/css" href="/Frontend/css/semantic/dist/semantic.min.css">';
        echo '    <link rel="stylesheet" type="text/css" href="/Frontend/css/style.css">  
        
';

        echo "<div class='' ui grid container'  style='overflow-x:auto'><table style='width:700px; height:500px;' class='ui compact unstackable table'></div>";
        foreach ($response as $key => $value)
        {
            if (strtoupper($sort['mesure']) == strtoupper($value['_source']['INTRO']['MEASUREMENT'][0]['ABBREVIATION']))
            {

                $file = $value['_source']['DATA']['FILES'][0]['ORIGINAL_DATA_URL'];
                $folder = explode('_', strtoupper($value['_source']['DATA']['FILES'][0]['DATA_URL']));
                $name = $value['_source']['DATA']['FILES'][0]['DATA_URL'];
                $file_parts = pathinfo($file);
                if ($file_parts['extension'] == "xlsx")
                {
                    $CSV_FOLDER = $config["CSV_FOLDER"];
                    $file = $CSV_FOLDER . $folder[0] . '_' . $folder[1] . "/" . $name;
                }
                $csv = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $finalcsv[] = $csv;
            }
        }
        foreach ($finalcsv as $key => $value)
        {
            foreach ($value as $key => $value)
            {
                $finalcsvuniq[] = $value;
            }
        }
        $finalcsvuniq = array_unique($finalcsvuniq);
        foreach ($finalcsvuniq as $key => $value)
        {
            $value = str_getcsv($value);
            echo "<tr>";
            foreach ($value as $cell)
            {
                echo "<td>" . htmlspecialchars($cell) . "</td>";
            }
            echo "</tr>\n";

        }
        echo "\n</table></body></html>";

    }

    function Download_data_with_sort($sort)
    {
        $lithology = '';
        $mesure = '';
        $sort = json_decode($sort, true);
        if ($sort['lithology'])
        {
           $lithology = 'INTRO.SUPPLEMENTARY_FIELDS.LITHOLOGY:"' . urlencode($sort['lithology']) . '"%20AND%20';
        }
        if ($sort['mindate'] and $sort['maxdate'])
        {
            $date = 'INTRO.SAMPLING_DATE:[' . $sort['mindate'] . '%20TO%20' . $sort['maxdate'] . ']%20AND%20';
        }
        if ($sort['mesure'])
        {
            $mesure = 'INTRO.MEASUREMENT.ABBREVIATION:"' . urlencode($sort['mesure']) . '"%20AND%20';
        }
        $config = self::ConfigFile();
        $url = $config['ESHOST'] . '/' . $config['INDEX_NAME'] . "/_search?q=" . $lithology . $mesure . $date . "type=" . $config['COLLECTION_NAME'] ."&size=10000";

        $postcontent = '{ "_source": { 
            "includes": [ "DATA","INTRO.MEASUREMENT.ABBREVIATION" ] 
             }}';
        $curlopt = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_PORT => $config['ESPORT'],
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $postcontent
        );
        $response = self::Curlrequest($url, $curlopt);
        $response = json_decode($response, true);
        $response = $response['hits']['hits'];
        $responses = array();
        $finalcsv = array();
        $finalcsvuniq = array();

        foreach ($response as $key => $value)
        {
            if (strtoupper($sort['mesure']) == strtoupper($value['_source']['INTRO']['MEASUREMENT'][0]['ABBREVIATION']) or $sort['mesure'] == null)
            {

                $file = $value['_source']['DATA']['FILES'][0]['ORIGINAL_DATA_URL'];
                $folder = explode('_', strtoupper($value['_source']['DATA']['FILES'][0]['DATA_URL']));
                $name = $value['_source']['DATA']['FILES'][0]['DATA_URL'];
                $file_parts = pathinfo($file);
                if ($file_parts['extension'] == "xlsx")
                {
                    $CSV_FOLDER = $config["CSV_FOLDER"];
                    $file = $CSV_FOLDER . $folder[0] . '_' . $folder[1] . "/" . $name;
                }
                $csv = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $finalcsv[] = $csv;
            }
        }
        foreach ($finalcsv as $key => $value)
        {
            foreach ($value as $key => $value)
            {
                $finalcsvuniq[] = $value;
            }
        }
        $finalcsvuniq = array_unique($finalcsvuniq);
        $generatedfile = "";
        foreach ($finalcsvuniq as $key => $value)
        {
            $generatedfile .= $value . "\n";
        }
        echo $generatedfile;

    }

    /**
     *  Methode de requetes vers elasticsearch
     *
     *
     */
    function Request_poi_with_sort($sort)
    {
        $lithology = '';
        $mesure = '';
        $sort = json_decode($sort, true);
        if ($sort['lithology'])
        {
            $lithology = 'INTRO.SAMPLE_KIND.NAME:"' . urlencode($sort['lithology']) . '"%20AND%20';
        }
        if ($sort['mindate'] and $sort['maxdate'])
        {
            $date = 'INTRO.SAMPLING_DATE:[' . $sort['mindate'] . '%20TO%20' . $sort['maxdate'] . ']%20AND%20';
        }
        if ($sort['mesure'])
        {
            $mesure = 'INTRO.MEASUREMENT.ABBREVIATION:"' . urlencode($sort['mesure']) . '"%20AND%20';
        }
        /*if ($sort['lat'] and $sort['lon']) {
        	$geo='INTRO.SAMPLING_POINT.LONGITUDE:['.abs($sort['lat']['lat1']).'%20TO%20'.abs($sort['lat']['lat2']).']%20AND%20INTRO.SAMPLING_POINT.LATITUDE:['.abs($sort['lon']['lon1']).'%20TO%20'.abs($sort['lon']['lon2']).']';
        }*/

        $config = self::ConfigFile();
        $url = $config['ESHOST'] . '/' . $config['INDEX_NAME'] . "/_search?q=" . $lithology . $mesure . $date . "type=" . $config['COLLECTION_NAME'] ."&size=10000";
        $postcontent = '{ "_source": { 
            "includes": [ "INTRO.SAMPLING_DATE","INTRO.TITLE","INTRO.SUPPLEMENTARY_FIELDS.LITHOLOGY","INTRO.SUPPLEMENTARY_FIELDS.DESCRIPTION","INTRO.SUPPLEMENTARY_FIELDS.SAMPLE_NAME","INTRO.SUPPLEMENTARY_FIELDS.ALTERATION_DEGREE","INTRO.SUPPLEMENTARY_FIELDS.NAME_REFERENT","INTRO.SUPPLEMENTARY_FIELDS.FIRST_NAME_REFERENT",
            "INTRO.SAMPLING_DATE","INTRO.SAMPLING_POINT","INTRO.MEASUREMENT","DATA.FILES" ] 
             }}';
        $curlopt = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_PORT => $config['ESPORT'],
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $postcontent
        );
        $response = self::Curlrequest($url, $curlopt);
        $response = json_decode($response, true);
        $response = $response['hits']['hits'];
        $responses = array();
        $return = array();
        foreach ($response as $key => $value)
        {
             foreach ($value['_source']['INTRO']['SAMPLING_POINT'] as $key => $value2) {
                
            $current = $value2['ABBREVIATION'];
            if (!$return[$value2['ABBREVIATION']])
            {
                $return[$value2['ABBREVIATION']]['SAMPLING_DATE'] = $value['_source']['INTRO']['SAMPLING_DATE'][0];
                //$return[$value2['ABBREVIATION']]['SUPPLEMENTARY_FIELDS'] = $value['_source']['INTRO']['SUPPLEMENTARY_FIELDS'];
                $return[$value2['ABBREVIATION']]['STATION'] = $value2['ABBREVIATION'];
                $return[$value2['ABBREVIATION']]['TITLE'] = $value['_source']['INTRO']['TITLE'];
                $return[$value2['ABBREVIATION']]['SAMPLING_POINT'][0]['LATITUDE'] = $value2['LATITUDE'];
                $return[$value2['ABBREVIATION']]['SAMPLING_POINT'][0]['DESCRIPTION'] = $value2['DESCRIPTION'];
                $return[$value2['ABBREVIATION']]['SAMPLING_POINT'][0]['LONGITUDE'] = $value2['LONGITUDE'];
                $return[$value2['ABBREVIATION']]['SAMPLING_POINT'][0]['SYSTEM'] = $value2['COORDINATE_SYSTEM'];

                foreach ($value['_source']['DATA']['FILES'] as $key => $file)
                {
                    if (exif_imagetype($file['ORIGINAL_DATA_URL']))
                    {
                        $return[$current]['PICTURES'][$key] = $file;
                    }
                }
            }

            $return[$value2['ABBREVIATION']]['MEASUREMENT'] = $value['_source']['INTRO']['MEASUREMENT'];
            }

                $responses = $return;
            }
        
        $responses = json_encode($responses);
        return $responses;
    }

    function Request_poi_data($id)
    {
        $explode = explode('_', $id, 2);
        $config = self::ConfigFile();
        $url = $config['ESHOST'] . '/' . $config['INDEX_NAME'] . '/_search?q=(INTRO.MEASUREMENT.ABBREVIATION:"' . $explode[1] . '"%20AND%20INTRO.SUPPLEMENTARY_FIELDS.SAMPLE_NAME:"' . $explode[0] . '")&type=' . $config['COLLECTION_NAME'] ;
        $curlopt = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_PORT => $config['ESPORT'],
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET"
        );
        $response = self::Curlrequest($url, $curlopt);
        $response = json_decode($response, true);
        $identifier = $response['hits']['hits'][0]['_source']['INTRO']['SUPPLEMENTARY_FIELDS']['SAMPLE_NAME'] . '_' . $response['hits']['hits'][0]['_source']['INTRO']['MEASUREMENT'][0]['ABBREVIATION'];
        if ($identifier == $id)
        {
            $response = json_encode($response['hits']['hits'][0]['_source']['DATA']);
            return $response;
        }
    }

     function Request_poi_raw_data($id)
    {
        $config = self::ConfigFile();
        $url = $config['ESHOST'] . '/' . $config['INDEX_NAME'] . '/_search?q=INTRO.MEASUREMENT.ABBREVIATION:"' . $id .'"&type='.$config['COLLECTION_NAME'];
        $curlopt = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_PORT => $config['ESPORT'],
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET"
        );
        $response = self::Curlrequest($url, $curlopt);
        $response = json_decode($response, true);
        
            if (count($response['hits']['hits'])==1) {
            $response = json_encode($response['hits']['hits'][0]['_source']['DATA']);
            return $response;
            }
        
    }


    function Request_poi_img($id, $picturename)
    {
        $explode = explode('_', $id, 2);
        $config = self::ConfigFile();
        $url = $config['ESHOST'] . '/' . $config['INDEX_NAME'] . '/_search?q=(INTRO.MEASUREMENT.ABBREVIATION:"' . $explode[1] . '"%20AND%20INTRO.SUPPLEMENTARY_FIELDS.SAMPLE_NAME:"' . $explode[0] . '")&type='. $config['COLLECTION_NAME'] ;
        $curlopt = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_PORT => $config['ESPORT'],
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET"
        );
        $response = self::Curlrequest($url, $curlopt);
        $response = json_decode($response, true);
        foreach ($response['hits']['hits'][0]['_source']['DATA']['FILES'] as $key => $value)
        {

            if ($value['DATA_URL'] == $picturename)
            {
                $response = $value['ORIGINAL_DATA_URL'];
            }

        }
        return $response;
    }

    /**
     * Download a file
     * @return true if ok else false
     */
    function download($filepath)
    {
        if (file_exists($filepath))
        {
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            header("Content-Disposition: attachment; filename=" . basename($filepath));
            $readfile = file_get_contents($filepath);
            print $readfile;
        }
        if ($readfile == false)
        {
            return false;
        }
        else
        {
            return true;
        }
        exit;
    }

    function preview_img($path)
    {
        $mime = pathinfo($path);
        $mime = $mime['extension'];
        $mime = strtolower($mime);
        if ($mime == 'png')
        {
            $readfile = readfile($path);
            $mime = "image/png";
            header('Content-Type:  ' . $mime);
        }
        elseif ($mime == 'jpg')
        {
            $readfile = readfile($path);
            $mime = "image/jpg";
            header('Content-Type:  ' . $mime);
        }
        elseif ($mime == 'gif')
        {
            $readfile = readfile($path);
            $mime = "image/gif";
            header('Content-Type:  ' . $mime);
        }
        else
        {
            echo "<h1>Cannot preview file</h1> <p>Sorry, we are unfortunately not able to preview this file.<p>";
            $readfile = false;
            header('Content-Type:  text/html');
        }
        if ($readfile == false)
        {
            return false;
        }
        else
        {
            return $mime;
        }
    }

    /**
     * Preview a file
     * @param doi of dataset, filename,data of dataset
     * @return true if ok else false
     */
    function preview($file, $folder, $name)
    {
        $config = self::ConfigFile();

        $file_parts = pathinfo($file);
        if ($file_parts['extension'] == "xlsx")
        {
            $CSV_FOLDER = $config["CSV_FOLDER"];
            $file = $CSV_FOLDER . $folder . "/" . $name;
        }
        if (file_exists($file))
        {

            $readfile = false;
            $file = fopen($file, "r");
            $firstTimeHeader = true;
            $firstTimeBody = true;
            echo '<link rel="stylesheet" type="text/css" href="/Frontend/src/css/semantic/dist/semantic.min.css">';
            echo '    <link rel="stylesheet" type="text/css" href="/Frontend/src/css/style.css">';
            echo "<div class='' ui grid container'  style='overflow-x:auto'><table style='width:700px; height:500px;' class='ui compact unstackable table'></div>";
            while (!feof($file))
            {
                $data = fgetcsv($file);

                if ($firstTimeHeader)
                {
                    echo "<thead>";
                }
                else
                {
                    if ($firstTimeBody)
                    {
                        echo "</thead>";
                        echo "<tbody>";
                        $firstTimeBody = false;
                    }
                }
                echo "<tr>";

                foreach ($data as $value)
                {
                    if ($firstTimeHeader)
                    {
                        echo "<th>" . $value . "</th>";
                    }
                    else
                    {
                        echo "<td>" . $value . "</td>";
                    }
                }

                echo "</tr>";
                if ($firstTimeHeader)
                {
                    $firstTimeHeader = false;
                }
            }
            echo "</table>";

        }

        else
        {
            echo "<h1>Cannot preview file</h1> <p>Sorry, we are unfortunately not able to preview this file.<p>";
            $readfile = false;
            header('Content-Type:  text/html');
        }

        if ($readfile == false)
        {
            return false;
        }
        else
        {
            return $mime;
        }
        exit;

    }

}

?>