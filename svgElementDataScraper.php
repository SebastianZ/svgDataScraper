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


  function formatAttributeName($name) {
  	// Remove hash sign at the beginning
  	$formattedName = substr($name, 1);

  	// Replace underscores by spaces
  	$formattedName = str_replace('_', ' ', $formattedName);

  	// Fix casing
  	$formattedName = lcfirst(ucwords($formattedName));

  	// Remove spaces and the word 'Attributes'
  	$formattedName = str_replace(['Attributes', ' '], '', $formattedName);

  	// Append 'Attributes'
  	$formattedName .= 'Attributes';
  	 
  	return $formattedName;
  }


  $svgData = new svgData();

  $locales = [
    'en-US' => [
      'permittedContent' => 'Permitted content'
    ],
    'bn-BD' => [
      'permittedContent' => 'Permitted content'
    ],
    'de' => [
      'permittedContent' => 'Erlaubter Inhalt'
    ],
    'es' => [
      'permittedContent' => 'Contenido permitido'
    ],
    'fr' => [
      'permittedContent' => 'Contenu autorisé|Contenu authorisé|Contenu permis|Contenu permit|Contenu'
    ],
    'ja' => [
      'permittedContent' => '許可された内容|利用可能な中身'
    ],
    'ko' => [
      'permittedContent' => 'Permitted content'
    ],
    'pt-BR' => [
      'permittedContent' => 'Conteúdo permitido|Contepudo permitido'
    ],
    'ru' => [
      'permittedContent' => 'Permitted content'
    ],
    'zh-CN' => [
      'permittedContent' => '允许的内容物|允许的内容|允许的子元素|允许内容'
    ]
  ];
  $elementReferenceURL = 'https://developer.mozilla.org/%s/docs/Web/SVG/Element/';
  $outputFolder = 'output';

  if (!file_exists($outputFolder)) {
    mkdir($outputFolder);
  }
  foreach ($locales as $locale => $localeItems) {
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

        if (preg_match('/<table class="standard-table">(?:.+?)<\/table>/s', $response, $infoTableMatches)) {
          // Parse categories
          if ($locale === 'en-US' && preg_match('/Categories.+?<td>(.+?)<\/td>/s', $infoTableMatches[0], $categoriesMatches)) {
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
          if (preg_match('/(?:' . $locales['en-US']['permittedContent'] . '|' .
              $localeItems['permittedContent'] . ').+?<td>(.+?)<\/td>/su', $infoTableMatches[0], $contentMatches)) {
            if (!isset($svgData->elements[$element]->content->description)) {
              $svgData->elements[$element]->content->description = [];
            }
            $svgData->elements[$element]->content->description[$locale] = $contentMatches[1];
            if (preg_match('/^(.+?)(<br\/?>\n\s+<a\s+href=".+?">.+?<\/a>.*)+$/su',
                $contentMatches[1], $contentElementListMatches)) {
              $svgData->elements[$element]->content->description[$locale] = $contentElementListMatches[1];
              if ($locale === 'en-US') {
                preg_match_all('/<a href=".+?["\']>(?:<code>)?(.*?)(?:<\/code>)?<\/a>/', $contentElementListMatches[2], $elementListMatches);
                $elements = array_map(function($element) {
                  if ($element[0] === '<') {
                    return $element;
                  }

                  $words = explode(' ', $element);
                  foreach ($words as $index => $word) {
                    $words[$index] = ($index === 0) ? lcfirst($word) : ucfirst($word);
                  }
                  return implode('', $words);
                }, $elementListMatches[1]);

                $svgData->elements[$element]->content->elements = $elements;
              }
            }
          }

          // Parse attributes
          if ($locale === 'en-US' && preg_match('/<h2[^>]*>Attributes<\/h2>.*?(?=<h2)/s', $response, $attributesMatches)) {
            preg_match_all('/<a.*?href="(.*?)"/', $attributesMatches[0], $attributeURLs);
  
            foreach ($attributeURLs[1] as $attributeURL) {
              $attribute = preg_replace('/^.+\/Attribute/', '', $attributeURL);
              if ($attribute[0] === '#') {
                $attributeType = formatAttributeName($attribute);
                array_push($svgData->elements[$element]->attributes, $attributeType);
              } else {
                array_push($svgData->elements[$element]->attributes, '\'' . substr($attribute, 1) . '\'');
              }
            }
          }

          // Parse DOM interfaces
          if ($locale === 'en-US' && preg_match('/<h2[^>]*?id="DOM_[iI]nterface"[^>]*>.*?(?=<h2)/s', $response, $domInterfacesMatches)) {
            preg_match_all('/<a.*?href="\/.*?\/(?:API|DOM)\/(.+?)"/', $domInterfacesMatches[0], $domInterfacesURLs);
            foreach ($domInterfacesURLs[1] as $domInterfacesURL) {
              array_push($svgData->elements[$element]->interfaces, $domInterfacesURL);
            }
            $svgData->elements[$element]->interfaces = array_unique($svgData->elements[$element]->interfaces);
          }
        }
      }
    }
  }

  file_put_contents($jsonFileName, json_encode($svgData, JSON_PRETTY_PRINT));

  dump($svgData);
?>