<?php
namespace Bitrix\Main\Controller;

use Bitrix\Main\Entity\ReferenceField;
use Bitrix\Main\Error;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Numerator\Generator\SequentNumberGenerator;
use Bitrix\Main;
use Bitrix\Main\Result;

/**
 * Class Numerator
 * @package Bitrix\Main\Controller
 */
class Numerator extends Main\Engine\Controller
{
	/**
	 * @param $id
	 * @return Result
	 * @throws \Bitrix\Main\ArgumentException
	 * @throws \Bitrix\Main\ObjectPropertyException
	 * @throws \Bitrix\Main\SystemException
	 */
	private function updateNextSequentialNumber($id)
	{
		$request = $this->getRequest();

		$sequenceConfig = $request->getPost(SequentNumberGenerator::getType());

		if (
			$sequenceConfig !== null
			&&
			is_array($sequenceConfig)
			&&
			array_key_exists('nextNumberForSequence', $sequenceConfig)
			&&
			$sequenceConfig['nextNumberForSequence']
		)
		{
			$nextNumber = $sequenceConfig['nextNumberForSequence'];
			if (is_numeric($nextNumber))
			{
				$sequence = Main\Numerator\Model\NumeratorTable::query()
					->registerRuntimeField(
						'',
						new ReferenceField(
							'ref',
							Main\Numerator\Model\NumeratorSequenceTable::class,
							['=this.ID' => 'ref.NUMERATOR_ID']
						)
					)
					->addSelect(('ID'))
					->addSelect('ref.NEXT_NUMBER', 'NEXT_NUMBER')
					->addSelect('ref.TEXT_KEY', 'TEXT_KEY')
					->where('ID', $id)
					->exec()
					->fetchAll();

				if ($sequence && count($sequence) == 1)
				{
					$dbNextNumber = $sequence[0]['NEXT_NUMBER'];
					if ((int)$nextNumber <= (int)$dbNextNumber)
					{
						return (new Result())
							->addError(new Error(Loc::getMessage('MAIN_NUMERATOR_EDIT_ERROR_NUMBER_LESS')));
					}
					$numerator = Main\Numerator\Numerator::load($id);
					if ($numerator)
					{
						$res = $numerator->setNextSequentialNumber($nextNumber, $dbNextNumber, $sequence[0]['TEXT_KEY']);
						if (!$res->isSuccess())
						{
							$errors = $res->getErrors();
							return (new Result())
								->addError($errors[0]);
						}
					}
				}
			}
			else
			{
				return (new Result())
					->addError(new Error(Loc::getMessage('MAIN_NUMERATOR_EDIT_ERROR_NUMBER_NOT_NUMERIC')));
			}
		}
		return (new Result());
	}

	/**
	 * @return array
	 * @throws \Bitrix\Main\ArgumentException
	 * @throws \Bitrix\Main\NotImplementedException
	 * @throws \Bitrix\Main\SystemException
	 */
	public function saveAction()
	{
		$request = $this->getRequest();

		$numeratorConfig = $request->getPost(Main\Numerator\Numerator::getType());
		$id = $numeratorConfig ['id'];
		if ($id)
		{
			$result = $this->updateNextSequentialNumber($id);
			if ($result->isSuccess())
			{
				$result = Main\Numerator\Numerator::update($id, $request->getPostList()->toArray());
			}
		}
		else
		{
			$numerator = Main\Numerator\Numerator::create();
			$result = $numerator->setConfig($request->getPostList()->toArray());
			if ($result->isSuccess())
			{
				$result = $numerator->save();
				$id = $result->getId();
			}
		}

		if (!$result->isSuccess())
		{
			foreach ($result->getErrorCollection() as $index => $error)
			{
				$this->errorCollection[] = $error;
			}
			return [];
		}
		return ['id' => $id];
	}
}