<?php

namespace App\Console\Commands;

use App\Http\Controllers\CrawlController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Imports\ExcelImport;
use App\Exports\ExcelExport;
use Hedii\Extractors\Extractor;

use App\Url;
use Illuminate\Support\Arr;


class Scrappy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrap:emails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scraps Emails from CSV file and creates a CSV';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
       
        //dd(file_exists());
        //dd(Storage::url('public/plus150.csv'));
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '3000');      
        $file = storage_path('app/public/plus150.csv');//'storage/app/public/plus150.csv';
        $file = $this->ask("path for the file?", $file);
        $this->info("Importing File from the location: ".$file);
        $data = \Excel::toCollection(new ExcelImport, new \Illuminate\Http\UploadedFile($file, 'plus150') , null, \Maatwebsite\Excel\Excel::CSV);

        //dd($data[0][1]);
        //$data = collect($data[0]);
        $this->info("Data Imported successfully, Initiating Scrappy...");
        //dd($data);
        $data = collect($data[0]);
        $newData=Collect();
        $this->output->progressStart(count($data));
        $data->each(function($record, $key) use ($newData) {
           
            if($key==0){
                $newData->put($key,$record);
            }
            else{

            $emails=implode(',', $this->runscrapper($record[6]));
            //var_dump();
            $record[]=$emails;
            $this->output->progressAdvance();
            $newData->put($key,$record);
        }
            
        });
        
        \Excel::store(new ExcelExport($newData), 'excel_with_emails.xlsx');
        $this->output->progressFinish();
    }

    public function runscrapper($base_url) {

        //Crawler::create()->setCrawlObserver()->startCrawling($url);
        $emailList = [];
        /* $urls = [
            'http://www.jbs.act.edu.au',
            'https://www.screenrights.org',
            'https://www.viraljetani.com',
            'https://www.enhancetv.com.au'

        ]; */
        //foreach($urls as $key => $base_url){
            
            //$newUrls=$this->crawl($base_url, $entryPoint = true);
            //dd($newUrls);
            // next, we crawl all search's urls
            /* while ($url = $this->getNextNotCrawledUrl()) {
                $this->crawl($url);

                // check if the search has been deleted during the crawl process
                //if ($this->searchMustEnd()) {
                //    return false;
                //}
            } */
            // get all the urls and emails on example.com page dom
            $pageUrls = array_unique($this->extractUrls($base_url));
            //var_dump($pageUrls);
            $allEmails = [];
            foreach($pageUrls as $key => $urlToSearch)
            {   
                $emails = array_unique($this->extractEmails($urlToSearch));
                if(sizeof($emails)>0 )
                {
                    array_push($allEmails,$emails);
                }
                if(sizeof($allEmails)>0 || sizeof($emails)==3){
                    break;
                }
            }
            //dd(array_unique(Arr::flatten($allEmails)));
            
            $emailList[] = Arr::flatten(array_unique(Arr::flatten($allEmails)));
        //}
        //var_dump($emailList);
        return Arr::flatten(array_unique(Arr::flatten($allEmails)));;
    }

    /**
     * A wrapper for extracting urls from a given base_url.
     *
     * @param string $base_url
     * @return array|null
     */
    public function extractUrls($base_url)
    {
        $extractor = new Extractor();
        $allUrls = $extractor->searchFor(['urls'])
        ->at($base_url)
        ->get();
        $urls = [];
        foreach (array_unique($allUrls['urls']) as $item) {
            $item = $this->cleanUrl($item);

            if ($this->canBeStored($item) && $this->isValidUrl($item) && $this->isNotMediaFile($item) && $this->isFromSameDomain($item, $base_url)) {
                
                //var_dump($item);
                array_push($urls,$item);
            }
        }
        //dd(array_unique($urls));

        return $urls;
    }

    public function extractEmails($url){

                // get all the emails on example.com page dom
        $extractor = new Extractor();
        $allEmails = $extractor->searchFor(['emails'])
        ->at($url)
        ->get();
        $emails = [];
        foreach (array_unique($allEmails['emails']) as $email) {
            if ($this->canBeStored($email) && $this->isValidEmail($email) && $this->isValidEmail($email)!='') {
                //Email::firstOrCreate(['name' => $email, 'search_id' => 1]);
                array_push($emails,$email);
                //var_dump($email);
            }
        }

        return array_unique($emails);

    }

    private function isFromSameDomain($url, $base_url): bool
    {
        if (!(strpos($url, $base_url) === false)) {
            if ((strpos($url, "http://") === false) || (strpos($url, "https://") === false)) {
                //var_dump($interiorURL);
                //var_dump($url);
                return true;
            } else {
                return false;
            }
        }
        else
        {
            return false;
        }
    }

    /**
     * A wrapper for php parse_url host function.
     *
     * @param string $url
     * @return string|null
     */
    private function getDomainName(string $url): ?string
    {
        return parse_url($url, PHP_URL_HOST) ?: null;
    }

    /**
     * A wrapper for php filter_var url function.
     *
     * @param string $url
     * @return bool
     */
    private function isValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * A wrapper for php filter_var email function.
     *
     * @param string $email
     * @return bool
     */
    private function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Remove unwanted character at the end of the url string,
     * and remove anchors in the url.
     *
     * @param string $url
     * @return string
     */
    private function cleanUrl(string $url): string
    {
        $url = rtrim(rtrim($url, '#'), '/');

        $fragment = parse_url($url, PHP_URL_FRAGMENT);

        if (! empty($fragment)) {
            $url = str_replace("#{$fragment}", '', $url);
        }

        return rtrim($url, '?');
    }

    /**
     * Whether a given url is a media file url.
     *
     * @param string $url
     * @return bool
     */
    private function isMediaFile(string $url): bool
    {
        return ends_with($url, [
            '.jpg', '.jp2', '.jpeg', '.raw', '.png', '.gif', '.tiff', '.bmp',
            '.svg', '.fla', '.swf', '.css', '.js', '.mp3', '.aac', '.wav',
            '.wma', '.aac', '.mp4', '.ogg', '.oga', '.ogv', '.flac', '.fla',
            '.ape', '.mpc', '.aif', '.aiff', '.m4a', '.mov', '.avi', '.wmv',
            '.qt', '.mp4a', '.mp4v', '.flv', '.ogm', '.mkv', '.mka', '.mks'
        ]);
    }

    /**
     * Whether a given url is a not media file url.
     *
     * @param string $url
     * @return bool
     */
    private function isNotMediaFile(string $url): bool
    {
        return ! $this->isMediaFile($url);
    }

    /**
     * Whether a value can be stored in a mysql varchar field.
     *
     * @param string $value
     * @return bool
     */
    private function canBeStored(string $value): bool
    {
        return strlen($value) <= 255;
    }

}
