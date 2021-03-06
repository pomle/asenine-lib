<?php
namespace Asenine\Media\Type;

use \Asenine\Disk\File;

class Image extends _Visual
{
	const TYPE = ASENINE_MEDIA_TYPE_IMAGE;
	const DESCRIPTION = 'Image / Graphic';

	public static function canHandleFile($filePath)
	{
		return \Asenine\App\ImageGuru::isValidFile($filePath);
	}

	public static function createFromFile(File $File)
	{
		if( $Image = parent::createFromFile($File) )
		{
			$info = $Image->getInfo();
			$Image->orientation = $info['orientation'];
			return $Image;
		}

		return false;
	}


	public function getAspectRatio()
	{
		$pixelsX = $this->getPixelsX();
		$pixelsY = $this->getPixelsY();

		if ($pixelsX > 0 && $pixelsY > 0) {
			return $pixelsX / $pixelsY;
		}

		return null;
	}

	public function getFrame($index = 0)
	{
		return $this->getFilePath();
	}

	public function getFrameCount()
	{
		return 1;
	}

	public function getInfo()
	{
		if(!isset($this->imageInfo)) {
			$this->imageInfo = \Asenine\App\ImageGuru::doIdentify($this->getFilePath(), true);
		}

		return $this->imageInfo;
	}

	public function getPreviewImage()
	{
		return $this->getFrame();
	}

	public function getPixelsX()
	{
		$info = $this->getInfo();
		return (int)$info['size']['x'];
	}

	public function getPixelsY()
	{
		$info = $this->getInfo();
		return (int)$info['size']['y'];
	}
}