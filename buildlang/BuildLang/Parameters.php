<?php
/**
 * Akeeba Build Files
 *
 * @package    buildfiles
 * @copyright  (c) 2010-2017 Akeeba Ltd
 */

namespace Akeeba\BuildLang;
use Akeeba\Engine\Postproc\Connector\S3v4\Configuration;
use Akeeba\Engine\Postproc\Connector\S3v4\Connector;

/**
 * Read only configuration parameters repository
 *
 * @property-read  string    $packageName
 * @property-read  string    $packageNameURL
 * @property-read  string    $softwareName
 * @property-read  string    $authorName
 * @property-read  string    $authorUrl
 * @property-read  string    $license
 * @property-read  string    $prototypeHTML
 * @property-read  string    $s3Access
 * @property-read  string    $s3Private
 * @property-read  string    $s3Signature
 * @property-read  string    $s3Bucket
 * @property-read  string    $s3Region
 * @property-read  string    $s3Path
 * @property-read  string    $s3CDNHostname
 * @property-read  string    $version
 * @property-read  Connector $s3
 */
class Parameters
{
	private $packageName;

	private $softwareName;

	private $authorName = 'Akeeba Ltd';

	private $authorUrl = 'https://www.akeeba.com';

	private $license = 'GNU GPL v3 or later';

	private $packageNameURL;

	private $s3Access;

	private $s3Private;

	private $s3Signature = 'v2';

	private $s3Bucket;

	private $s3Region = '';

	private $s3Path;

	private $s3CDNHostname = 'cdn.akeebabackup.com';

	private $version;

	private $prototypeHTML = 'translations/_pages/index.html';

	/**
	 * A connector to Amazon S3
	 *
	 * @var  Connector
	 */
	private $_s3Connector;

	/**
	 * Parameters constructor.
	 *
	 * @param   string  $iniFile          The INI file(s) to load properties from
	 * @param   array   $extraProperties  Any additional properties to pass
	 */
	public function __construct(string $iniFile, array $extraProperties = [])
	{
		$properties = $this->parseFile($iniFile);
		$properties = array_merge($properties, $extraProperties);

		$this->initializeFromArray($properties);
	}

	/**
	 * Initialize this object from an array of values, presumably one read from the provided INI file
	 *
	 * @param   array $values
	 *
	 * @return  void
	 */
	private function initializeFromArray(array $values)
	{
		$map = [
			'langbuilder.packagename' => 'packageName',
			'langbuilder.software'    => 'softwareName',
			'langbuilder.authorname'  => 'authorName',
			'langbuilder.authorurl'   => 'authorUrl',
			'langbuilder.license'     => 'license',
			'langbuilder.protohtml'   => 'prototypeHTML',
			's3.access'               => 's3Access',
			's3.private'              => 's3Private',
			's3.signature'            => 's3Signature',
			's3.bucket'               => 's3Bucket',
			's3.region'               => 's3Region',
			's3.path'                 => 's3Path',
			's3.cdnhostname'          => 's3CDNHostname',
			'extra.version'           => 'version',
		];

		foreach ($map as $arrayKey => $propertyName)
		{
			if (!isset($values[$arrayKey]))
			{
				continue;
			}

			if (!property_exists($this, $propertyName))
			{
				continue;
			}

			$this->{$propertyName} = $values[$arrayKey];
		}

		// Create an URL-friendly version of the package name
		$this->packageNameURL = str_replace(' ', '-', strtolower(trim($this->packageName)));

		// Create a default version number if none is specified
		if (empty($this->version))
		{
			$this->version = gmdate('Ymd.His');
		}
	}

	/**
	 * Parse a properties INI file. You can give it multiple files by separating their names with a semicolon.
	 *
	 * @param   string $iniFile The INI file(s) to read
	 *
	 * @return  array  The properties read from the file(s)
	 */
	private function parseFile(string $iniFile): array
	{
		if (strpos($iniFile, ';') === false)
		{
			$properties = parse_ini_file($iniFile);

			return (!is_array($properties) || empty($properties)) ? [] : $properties;
		}

		$fileList   = explode(';', $iniFile);
		$properties = [];

		foreach ($fileList as $iniFile)
		{
			if (!file_exists($iniFile))
			{
				continue;
			}

			$newProps   = $this->parseFile($iniFile);
			$properties = array_merge($properties, $newProps);
		}

		return $properties;
	}

	/**
	 * Magic getter. Allows read-only access to the private properties.
	 *
	 * @param   string $name
	 *
	 * @return  mixed
	 */
	public function __get(string $name)
	{
		if (property_exists($this, $name) && (substr($name, 0, 1) != '_'))
		{
			return $this->{$name};
		}

		if ($name === 's3')
		{
			return $this->getS3();
		}

		throw new \InvalidArgumentException("Property $name does not exist");
	}

	/**
	 * Get the cached S3 connector. Creates one if it doesn't already exist.
	 *
	 * @return  Connector
	 */
	public function getS3(): Connector
	{
		if (is_null($this->_s3Connector))
		{
			$config = new Configuration($this->s3Access, $this->s3Private, $this->s3Signature, $this->s3Region);

			$this->_s3Connector = new Connector($config);
		}

		return $this->_s3Connector;
	}
}
