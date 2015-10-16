<?php
  if (file_exists('PHPDump/src/debug.php')) {
    require_once 'PHPDump/src/debug.php';
  }

  set_time_limit(600);

  $jsonFileName = 'svgData.json';

  class svgData {
    public $elements = [];
  }

  class svgElement {
    public $categories = [];
    public $attributes = [];
    public $interfaces = [];
  }

  $svgData = new svgData();

  $locales = [
      'en-US',
      'bn-BD',
      'de',
      'es',
      'fr',
      'ja',
      'ko',
      'pt-BR',
      'ru',
      'zh-CN'
  ];
  $elementReferenceURL = 'https://developer.mozilla.org/%s/docs/Web/SVG/Element/';
  $outputFolder = 'output';

  if (!file_exists($outputFolder)) {
    mkdir($outputFolder);
  }
  foreach ($locales as $locale) {
    $filePath = $outputFolder . '/elementReference.' . $locale . '.html';
    if (isset($_GET['refresh']) || !file_exists($filePath)) {
      $fetchLocation = sprintf($elementReferenceURL, $locale);
    } else {
      $fetchLocation = $filePath;
    }

    $response = file_get_contents($fetchLocation);

    if (isset($_GET['refresh']) || !file_exists($filePath)) {
      file_put_contents($filePath, $response);
    }

    if (preg_match('/<div class="index">.+?<\/div>/s', $response, $indexMatch)) {
      preg_match_all('/<a href="(.+?)".+?>/', $indexMatch[0], $elementURLPathMatches);
      $elementURLPaths = [];
      foreach ($elementURLPathMatches[0] as $index => $match) {
        if (strpos($match, 'class="new"') === false) {
          array_push($elementURLPaths, $elementURLPathMatches[1][$index]);
        }
      }

      // Fetch each element page and parse it
      foreach ($elementURLPaths as $urlPaths) {
        preg_match('/.*\/(.+)$/', $urlPaths, $pageMatch);
        $element = $pageMatch[1];
        $filePath = $outputFolder . '/' . $element . '.' . $locale . '.html';
        if (isset($_GET['refresh']) || !file_exists($filePath)) {
          $fetchLocation = 'https://developer.mozilla.org' . $urlPaths;
        } else {
          $fetchLocation = $filePath;
        }

        if (!isset($svgData->elements[$element])) {
          $svgData->elements[$element] = new svgElement();
        }
      }
    }
  }

  file_put_contents($jsonFileName, json_encode($svgData, JSON_PRETTY_PRINT));
?>