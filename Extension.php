<?php


namespace Bolt\Extension\cdowdy\boltresponsiveimages;

use Bolt\Application;
use Bolt\BaseExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Bolt\Thumbs;

class Extension extends BaseExtension
{

	private $currentPictureFill = '3.0.1';

	public function initialize()
	{
		$this->addTwigFunction( 'respImg', 'respImg' );
	}

	/**
	 * @return string
	 */
	public function getName()
	{
		return "boltresponsiveimages";
	}


	// originally had  array $options = array() to pass in height and width options
	// but I can't get it to work because my PHP skills are baaaaad
	public function respImg( $file, $name, array $options = array() )
	{

		$this->addAssets();
		// get the config file name if using one. otherwise its 'default'
		$configName = $this->getConfigName( $name );

		// gather the default options, merge them with any options passed in the template
		$defaultOptions = $this->getOptions( $file, $configName, $options );

		$optionsWidths = $this->getOptions( $file, $configName, $options )[ 'widths' ];
		$optionHeights = $this->getOptions( $file, $configName, $options )[ 'heights' ];

		// get the alt text for the Image
		$altText = $this->getAltText( $configName, $file );
		// get size attribute if using the W descriptor
		$sizeAttrib = $this->getOptions( $file, $configName, $options )[ 'sizes' ];

		// Combine the Heights and Widths to use for our thumbnail parameters
		$sizeArray = $this->getCombinedSizes( $optionsWidths, $optionHeights );

		// get what we need for the cropping parameter
		$cropping = $this->getOptions( $file, $configName, $options )[ 'cropping' ];

		$densityWidth = $this->getOptions( $file, $configName, $options )[ 'widthDensity' ];

		// make thumbs an empty array
		$thumb = array();
		// loop through the size array and generate a thumbnail and URL
		// place those in an array to be used in the twig template
		foreach ( $sizeArray as $key => $value ) {
			$thumb[] .= $this->thumbnail( $file, $key, $value, $cropping );
			// . ' '. $densityWidth
		}

		// use the array below if using the W descriptor
		if ( $densityWidth == 'w' ) {
			$combinedImages = array_combine( $thumb, $optionsWidths );
		}
		// get the smallest (first sizes in the size array) heights and widths for the src image
		$srcThumbWidth = $this->getOptions( $file, $configName, $options )[ 'widths' ][ 0 ];
		$srcThumbHeight = $this->getOptions( $file, $configName, $options )[ 'heights' ][ 0 ];
//		$srcSizeArray = $this->getCombinedSizes( $srcThumbWidth, $srcThumbHeight);

		// if not using picturefill place the smallest image in the "src" attribute of the img tag
		// <img srcset="" src="smallest image here" alt="alt text" >
		$srcThumb = $this->thumbnail( $file, $srcThumbWidth, $srcThumbHeight, $cropping );

		// load up twig template directory
		$this->app[ 'twig.loader.filesystem' ]->addPath( __DIR__ . "/assets" );


		$support = $this->app[ 'render' ]->render( 'respimg.twig', array(
			'alt' => $altText,
			'sizes' => $sizeAttrib,
			'options' => $defaultOptions,
			'widthDensity' => $densityWidth,
			'combinedImages' => $combinedImages,
			'thumb' => $thumb,
			'srcThumb' => $srcThumb

		) );

		return new \Twig_Markup( $support, 'UTF-8' );
	}


	/**
	 * @param $name
	 *
	 * @return string
	 *
	 * get the config name. If no name is passed in the twig function then use
	 * the default settings in our config file under defaults
	 */
	function getConfigName( $name )
	{

		if ( empty( $name ) ) {

			$configName = 'default';

		} else {

			$configName = $name;

		}

		return $configName;
	}



	/*	function defaultOptions( $config, $option ) {
			$configName = $this->getConfigName( $config );


			if ( isset( $option ) && !empty( $option ) ) {
				$defOption = $this->config[$configName][$option];
			} else {
				$defOption = $this->config['default'][$option];
			}

			if ( is_null($option)) {
				$defOption = $this->config['default'][$option];
			}

			return $defOption;

		}*/

	/**
	 * @param       $filename
	 * @param       $config
	 * @param array $options
	 *
	 * @return array
	 *
	 * Get the default options
	 */
	function getOptions( $filename, $config, $options = array() )
	{

		$configName = $this->getConfigName( $config );
//		$defaultWidths   = $this->getImageWidths( $configName);
//		$defaultHeights = $this->getImageHeights( $configName);
		$defaultWidths = $this->getWidthsHeights( $configName, 'widths' );
		$defaultHeights = $this->getWidthsHeights( $configName, 'heights' );
		$cropping = $this->getCropping( $configName );
		$altText = $this->getAltText( $configName, $filename );
		$widthDensity = $this->getWidthDensity( $configName );
		$sizes = $this->getSizesAttrib( $configName );
		$class = $this->getHTMLClass( $configName );


		$defaults = array(
			'widths' => $defaultWidths,
			'heights' => $defaultHeights,
			'cropping' => $cropping,
			'widthDensity' => $widthDensity,
			'sizes' => $sizes,
			'altText' => $altText,
			'class' => $class,
		);

		$defOptions = array_merge( $defaults, $options );

		return $defOptions;
	}


	/**
	 * @param $config
	 * @param $filename
	 *
	 * @return mixed
	 */
	function getAltText( $config, $filename )
	{

		$configName = $this->getConfigName( $config );
		$altText = $this->config[ $configName ][ 'altText' ];

		if ( empty( $altText ) ) {
			$tempAltText = pathinfo( $filename );
			$altText = $tempAltText[ 'filename' ];
		}

		return $altText;
	}


	/**
	 * @param $config
	 * @param $option
	 *
	 * @return mixed
	 */
	function getWidthsHeights( $config, $option )
	{

		$configName = $this->getConfigName( $config );
		$configOption = $this->config[ $configName ][ $option ];

		if ( isset( $configOption ) && !empty( $configOption ) ) {
			$configParam = $this->config[ $configName ][ $option ];
		} else {
			$configParam = $this->config[ 'default' ][ $option ];
		}

		return $configParam;
	}

	/*
		function getImageWidths( $config ) {

			$configName = $this->getConfigName( $config );
			$configWidths = $this->config[ $configName ]['widths'];

			if ( isset( $configWidths ) && !empty($configWidths) ) {
				$widths = $this->config[$configName]['widths'];
			} else {
				$widths = $this->config['default']['widths'];
			}

			return $widths;
		}

		function getImageHeights( $config ) {

			// get the config name
			$configName = $this->getConfigName( $config );
			// then the config heights
			$configHeights = $this->config[ $configName ]['heights'];

			if ( isset( $configHeights ) && !empty($configHeights) ) {
				$heights = $this->config[$configName]['heights'];
			} else {
				$heights = $this->config['default']['heights'];
			}

			return $heights;
		}*/


	/**
	 * @param $widths
	 * @param $heights
	 *
	 * @return array
	 */
	function getCombinedSizes( $widths, $heights )
	{

		$widthCount = count( $widths );
		$heightCount = count( $heights );

		if ( $widthCount != $heightCount ) {
			$newWidthArray = array_pad( $widths, $heightCount, 0 );
		} else {
			$newWidthArray = $widths;
		}

		if ( $heightCount != $widthCount ) {
			$newHeightArray = array_pad( $heights, $widthCount, 0 );
		} else {
			$newHeightArray = $heights;
		}

		$combinedArray = array_combine( $newWidthArray, $newHeightArray );

		return $combinedArray;
	}


	/**
	 * @param $config
	 *
	 * @return mixed
	 */
	function getWidthDensity( $config )
	{
		$configName = $this->getConfigName( $config );
		$widthDensity = $this->config[ $configName ][ 'widthDensity' ];

		if ( isset( $widthDensity ) && !empty( $widthDensity ) ) {
			$wd = $this->config[ $configName ][ 'widthDensity' ];
		} else {
			$wd = $this->config[ 'default' ][ 'widthDensity' ];
		}

		return $wd;
	}

	/**
	 * @param $config
	 *
	 * @return mixed
	 */
	function getCropping( $config )
	{
		$configName = $this->getConfigName( $config );
		$cropping = $this->config[ $configName ][ 'cropping' ];

		if ( isset( $cropping ) && !empty( $cropping ) ) {
			$crop = $this->config[ $configName ][ 'cropping' ];
		} else {
			$crop = $this->config[ 'default' ][ 'cropping' ];
		}

		return $crop;
	}

	/**
	 * @param $config
	 *
	 * @return mixed
	 */
	function getSizesAttrib( $config )
	{
		$configName = $this->getConfigName( $config );
		$sizes = $this->config[ $configName ][ 'sizes' ];

		if ( isset( $sizes ) && !empty( $sizes ) ) {
			$sizesAttrib = $this->config[ $configName ][ 'sizes' ];
		} else {
			$sizesAttrib = $this->config[ 'default' ][ 'sizes' ];
		}

		return $sizesAttrib;
	}

	/**
	 * @param $config
	 *
	 * @return mixed
	 */
	function getHTMLClass( $config )
	{
		$configName = $this->getConfigName( $config );
		$htmlClass = $this->config[ $configName ][ 'class' ];

		if ( isset( $htmlClass ) && !empty( $htmlClass ) ) {
			$class = $this->config[ $configName ][ 'class' ];
		} else {
			$class = $this->config[ 'default' ][ 'class' ];
		}

		return $class;
	}

	/**
	 * @param        $filename
	 * @param string $width
	 * @param string $height
	 * @param string $zoomcrop
	 *
	 * @return mixed
	 */
	public function thumbnail( $filename, $width = '', $height = '', $zoomcrop )
	{
		$thumbConfig = $this->app[ 'config' ]->get( 'general/thumbnails' );

		if ( !is_numeric( $width ) ) {
			$width = empty( $thumbConfig[ 'default_thumbnail' ][ 0 ] ) ? 100 : $thumbConfig[ 'default_thumbnail' ][ 0 ];
		}

		if ( !is_numeric( $height ) ) {
			$height = empty( $thumbConfig[ 'default_thumbnail' ][ 1 ] ) ? 100 : $thumbConfig[ 'default_thumbnail' ][ 1 ];
		}


		switch ( $zoomcrop ) {
			case 'fit':
			case 'f':
				$scale = 'f';
				break;

			case 'resize':
			case 'r':
				$scale = 'r';
				break;

			case 'borders':
			case 'border':
			case 'b':
				$scale = 'b';
				break;

			case 'crop':
			case 'c':
				$scale = 'c';
				break;

			default:
				$scale = !empty( $thumbconf[ 'cropping' ] ) ? $thumbconf[ 'cropping' ] : 'c';
		}

		// After v1.5.1 we store image data as an array
		if ( is_array( $filename ) ) {
			$filename = isset( $filename[ 'filename' ] ) ? $filename[ 'filename' ] : $filename[ 'file' ];
		}


		$path = $this->app[ 'url_generator' ]->generate(
			'thumb',
			array(
				'thumb' => round( $width ) . 'x' . round( $height ) . $scale . '/' . $filename,
			)
		);

		return $path;
	}

	/**
	 * Add Picturefill to the current page!!!
	 */
	private function addAssets()
	{
		/**
		 * since there is no head function or any reliable way to insert anything in to the head in Bolt we have to
		 * hackishly insert picturefill into the head this way.
		 *
		 * first we assign a variable ($pictureFillJS) to the base URL
		 * then insert that variable into a heredoc
		 */
		$pictureFillJS = $this->getBaseUrl() . 'js/picturefill/' . $this->currentPictureFill . '/picturefill.min.js';
		$pictureFill = <<<PFILL
<script src="{$pictureFillJS}" async defer></script>
PFILL;

		if ( $this->config[ 'picturefill' ] == true ) {
			// insert snippet after the last CSS file in the head
			$this->addSnippet( 'afterheadcss', $pictureFill );
		}
		// for browsers that don't understand <picture> element
//        $picElement = <<<PICELEM
//<script>document.createElement( "picture" );</script>
//PICELEM;
//        // insert snippet after the last CSS file in the head
//        $this->addSnippet( 'afterheadcss', $picElement );
	}


	public function isSafe()
	{
		return true;
	}
}
