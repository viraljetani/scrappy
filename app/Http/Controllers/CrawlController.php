<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Crawler\Crawler;
use Goutte;
use Hedii\Extractors\Extractor;
use Hedii\Extractors\EmailExtractor;
use Hedii\Extractors\UrlExtractor;
use App\Search;
use App\Email;
use App\Url;
use Illuminate\Support\Arr;
use App\Imports\ExcelImport;
use App\Exports\ExcelExport;

class CrawlController extends Controller
{
    /**
     * Crawler constructor.
     *
     * @param \App\Search $search
     */
    public function __construct(Search $search)
    {
        //$search = collect($urls);
        $this->search = $search;
        $this->extractor = new Extractor();
    }

    public function run($base_url) {

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

    /* public function extract($innerURLS, $base_url) {
        $urlsAndEmails = ['urls','emails'];
        foreach ($innerURLS as $key=>$interiorURL) {
            if ( (strpos($interiorURL, "http://") === false) || (strpos($interiorURL, "https://") === false) || strpos($interiorURL, $base_url) === false) {
                //var_dump($interiorURL);
                $extractor= new Extractor();
                $additionalUrlsAndEmails = $extractor->searchFor(['urls', 'emails'])
                ->at($interiorURL)
                ->get();
                array_push($urlsAndEmails['urls'], $additionalUrlsAndEmails['urls']);
                array_push($urlsAndEmails['emails'], $additionalUrlsAndEmails['emails']);
            }
            
        }
    } */

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
     * Crawl an url and extract resources.
     *
     * @param mixed $url
     * @param bool $entryPoint
     */
    private function crawl($url, bool $entryPoint = false): void
    {
        $results = $this->extractor
            ->searchFor(['urls', 'emails'])
            ->at($entryPoint ? $url : $url->name)
            ->get();

        foreach (array_unique($results['urls']) as $item) {
            $item = $this->cleanUrl($item);

            if ($this->canBeStored($item) && $this->isValidUrl($item) && $this->isNotMediaFile($item)) {
                //Url::firstOrCreate(['name' => $item, 'search_id' => 1]);
                var_dump($item);
            }
        }

        foreach (array_unique($results['emails']) as $email) {
            if ($this->canBeStored($email) && $this->isValidEmail($email)) {
                //Email::firstOrCreate(['name' => $email, 'search_id' => 1]);
                var_dump($email);
            }
        }

        /* if (! $entryPoint) {
            $url->update(['is_crawled' => true]);
        } */
    }

    /**
     * Get the search's url that has not been crawled yet.
     *
     * @return \App\Url|null
     */
    private function getNextNotCrawledUrl(): ?Url
    {
        return $this->search->urls()
            ->notCrawled()
            ->first();
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
     * Whether the given url in the scope of the current search.
     *
     * @param string $url
     * @return bool
     */
    private function isInScope(string $url): bool
    {
        if (! $this->search->is_limited) {
            return true;
        }

        return $this->getDomainName($url) === $this->getDomainName($this->search->url);
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

    public function showForm(){
        return view('welcome');
    }

    public function storeDataFromExcel(Request $request){
        //dd($request->excel);
        //dd($request->excel->store('excel.xlsx'));
        //$stored = \Storage::put('excel.xlsx', $request->excel);
        //dd($request->excel);
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '3000');
        $data = \Excel::toCollection(new ExcelImport, $request->excel, 'local', \Maatwebsite\Excel\Excel::CSV);
        //$data = (new ExcelImport)->import($request->excel, 'local', \Maatwebsite\Excel\Excel::XLSX);
        //$data = Excel::load($path)->get();
        //$data = (new UsersImport)->import($path, null, \Maatwebsite\Excel\Excel::XLSX);
        //dd($data[0]);
        //$data = collect($data[0]);
        //dd($data);
        $data = collect($data[0]);
        $newData=Collect();
        $data->each(function($record, $key) use ($newData) {
            
            $emails=implode(',', CrawlController::run($record[13]));
            //var_dump();
            $record[]=$emails;
            //dd($record);
            $newData->put($key,$record);

        });
        //dd($newData);
        return \Excel::download(new ExcelExport($newData), 'excel_with_emails.xlsx');
    }
}
