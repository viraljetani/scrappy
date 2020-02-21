<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithStartRow;


class ExcelImport implements ToCollection, WithChunkReading, WithStartRow
{
    /**
    * @param Collection $collection
    */
    public function collection(Collection $collection)
    {
        //
        return $collection;
    }

     public function chunkSize(): int
    {
        return 500;
    }

    /**
     * @return int
     */
    public function startRow(): int
    {
        return 2;
    }
}
