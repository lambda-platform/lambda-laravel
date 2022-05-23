<?php
namespace Lambda\Datagrid;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\Exportable;

class ExportExcel implements FromCollection
{
    use Exportable;

    public $qr;
    public $header;

    public function __construct($qr, $header)
    {
        $this->qr = $qr;
        $this->header = $header;
    }

    public function collection()
    {
        $collection =  $this->qr->get();
        $collection->prepend($this->header);
        return $collection;
    }
}
