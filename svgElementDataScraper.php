<?php
  if (file_exists('PHPDump/src/debug.php')) {
    require_once 'PHPDump/src/debug.php';
  }

  set_time_limit(900);

  $jsonFileName = 'svgData.json';

  class svgData {
    public $elements = [];
  }

  class svgElement {
    public $categories = [];
    public $content = null;
    public $attributes = [];
    public $interfaces = [];

    public function __construct() {
    	$this->content = new svgElementContent();
    }
  }

  class svgElementContent {
  	public $description = '';
  }

  $svgData = new svgData();

  $locales = [
      'en-US'
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

        $response = file_get_contents($fetchLocation);

        if (isset($_GET['refresh']) || !file_exists($filePath)) {
          file_put_contents($filePath, $response);
        }

        preg_match('/<table class="standard-table">(?:.+?)<\/table>/s', $response, $infoTableMatches);
        if (preg_match('/Categories.+?<td>(.+?)<\/td>/s', $infoTableMatches[0], $categoriesMatches)) {
          $categories = explode(', ', $categoriesMatches[1]);
          $categories = array_map(function($category) {
            $words = explode(' ', $category);
            foreach ($words as $index => $word) {
              $words[$index] = ($index === 0) ? lcfirst($word) : ucfirst($word);
            }
            return implode('', $words);
          }, $categories);
          $svgData->elements[$element]->categories = $categories;
        }
        // Parse permitted content
        if (preg_match('/Permitted content.+?<td>(.+?)<\/td>/s', $infoTableMatches[0], $contentMatches)) {
          $svgData->elements[$element]->content->description = [];
          $svgData->elements[$element]->content->description[$locale] = $contentMatches[1];
        	if (preg_match('/^(.+?):(<br>\n\s+<a\s+href=".+?">.+?<\/a>.*)+$/s',
        			$contentMatches[1], $contentElementListMatches)) {
            $svgData->elements[$element]->content->description[$locale] = $contentElementListMatches[1];
            preg_match_all('/<a href=".+?">(?:<code>)?(.*?)(?:<\/code>)?<\/a>/', $contentElementListMatches[2], $elementListMatches);
            $svgData->elements[$element]->content->elements = $elementListMatches[1];
          }
        }

        // Parse attributes
        if (preg_match('/<h2[^>]*>Attributes<\/h2>.*?(?=<h2)/s', $response, $attributesMatches)) {
	        preg_match_all('/<a.*?href="(.*?)"/', $attributesMatches[0], $attributeURLs);

	        foreach ($attributeURLs[1] as $attributeURL) {
	          $attribute = preg_replace('/^.+\/Attribute/', '', $attributeURL);
	          if ($attribute[0] === '#') {
	          	$attributeType = lcfirst(substr($attribute, 1)) . 'Attributes';
	            array_push($svgData->elements[$element]->attributes, $attributeType);
	          } else {
	            array_push($svgData->elements[$element]->attributes, '\'' . substr($attribute, 1) . '\'');
	          }
	        }
        }

        // Parse DOM interfaces
        if (preg_match('/<h2[^>]*?id="DOM_[iI]nterface"[^>]*>.*?(?=<h2)/s', $response, $domInterfacesMatches)) {
	        preg_match_all('/<a.*?href="\/.*?\/(?:API|DOM)\/(.+?)"/', $domInterfacesMatches[0], $domInterfacesURLs);
        	foreach ($domInterfacesURLs[1] as $domInterfacesURL) {
	          array_push($svgData->elements[$element]->interfaces, $domInterfacesURL);
	        }
	        $svgData->elements[$element]->interfaces = array_unique($svgData->elements[$element]->interfaces);
        }
      }
    }
  }

  file_put_contents($jsonFileName, json_encode($svgData, JSON_PRETTY_PRINT));

  dump($svgData);
?>