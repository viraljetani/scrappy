<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;

class ExcelExport implements FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */
    protected $collection;

	public function __construct($collection)
	{
		$this->collection = $collection;
	}

	public function collection()
	{
		return $this->collection;
	}
}
