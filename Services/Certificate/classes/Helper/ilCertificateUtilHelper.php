<?php
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Just a wrapper class to create Unit Test for other classes.
 * Can be remove when the static method calls have been removed
 *
 * @author  Niels Theen <ntheen@databay.de>
 */
class ilCertificateUtilHelper
{
	/**
	 * @param string $data
	 * @param string $fileName
	 * @param string $mimeType
	 */
	public function deliverData(string $data, string $fileName, string $mimeType)
	{
		ilUtil::deliverData(
			$data,
			$fileName,
			$mimeType
		);
	}

	/**
	 * @param string $string
	 * @return string
	 */
	public function prepareFormOutput(string $string) : string
	{
		return ilUtil::prepareFormOutput($string);
	}

	/**
	 * @param string $from
	 * @param string $to
	 * @param string $targetFormat
	 * @param string $geometry
	 * @param string $backgroundColor
	 */
	public function convertImage(
		string $from,
		string $to,
		string $targetFormat = '',
		string $geometry = '',
		string $backgroundColor = ''
	) {
		return ilUtil::convertImage($from, $to, $targetFormat, $geometry, $backgroundColor);
	}
}
