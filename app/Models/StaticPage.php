<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StaticPage extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'static_pages';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'terms_title',
        'terms_description',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'updated_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the admin who last updated this page
     */
    public function updatedByAdmin()
    {
        return $this->belongsTo(Admin::class, 'updated_by');
    }

    /**
     * Get page by title
     * 
     * @param string $title Page title slug
     * @return StaticPage|null
     */
    public static function getByTitle($title)
    {
        return self::where('terms_title', $title)->first();
    }

    /**
     * Get or create page by title
     * 
     * @param string $title Page title slug
     * @return StaticPage
     */
    public static function getOrCreateByTitle($title)
    {
        return self::firstOrCreate(
            ['terms_title' => $title],
            ['terms_description' => '']
        );
    }

    /**
     * Update page content
     * 
     * @param string $title Page title slug
     * @param string $content Page content
     * @param int $adminId Admin ID who is updating
     * @return StaticPage
     */
    public static function updatePage($title, $content, $adminId = null)
    {
        $page = self::getOrCreateByTitle($title);
        $page->terms_description = $content;
        $page->updated_by = $adminId;
        $page->save();
        
        return $page;
    }

    /**
     * Get Terms & Conditions
     * 
     * @return StaticPage|null
     */
    public static function getTermsAndConditions()
    {
        return self::getByTitle('terms_and_conditions');
    }

    /**
     * Get Data Privacy Policy
     * 
     * @return StaticPage|null
     */
    public static function getPrivacyPolicy()
    {
        return self::getByTitle('data_privacy_policy');
    }

    /**
     * Get About Us
     * 
     * @return StaticPage|null
     */
    public static function getAboutUs()
    {
        return self::getByTitle('about_us');
    }

    /**
     * Get all available pages
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getAllPages()
    {
        return self::orderBy('terms_title')->get();
    }

    /**
     * Get pages with admin info
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getPagesWithAdmin()
    {
        return self::with('updatedByAdmin')->orderBy('terms_title')->get();
    }

    /**
     * Check if page exists
     * 
     * @param string $title Page title slug
     * @return bool
     */
    public static function pageExists($title)
    {
        return self::where('terms_title', $title)->exists();
    }

    /**
     * Get page title in human-readable format
     * 
     * @return string
     */
    public function getFormattedTitleAttribute()
    {
        return ucwords(str_replace('_', ' ', $this->terms_title));
    }

    /**
     * Get short description (first 200 characters)
     * 
     * @return string
     */
    public function getShortDescriptionAttribute()
    {
        return substr(strip_tags($this->terms_description), 0, 200) . '...';
    }

    /**
     * Check if page has content
     * 
     * @return bool
     */
    public function hasContent()
    {
        return !empty($this->terms_description);
    }

    /**
     * Get last updated time in human-readable format
     * 
     * @return string
     */
    public function getLastUpdatedAttribute()
    {
        return $this->updated_at->diffForHumans();
    }

    /**
     * Scope to get pages updated by specific admin
     */
    public function scopeByAdmin($query, $adminId)
    {
        return $query->where('updated_by', $adminId);
    }

    /**
     * Scope to get pages with content
     */
    public function scopeHasContent($query)
    {
        return $query->whereNotNull('terms_description')
            ->where('terms_description', '!=', '');
    }

    /**
     * Get pages updated recently
     * 
     * @param int $days Number of days
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getRecentlyUpdated($days = 30)
    {
        return self::where('updated_at', '>=', now()->subDays($days))
            ->orderBy('updated_at', 'desc')
            ->get();
    }

    /**
     * Get page statistics
     * 
     * @return array
     */
    public static function getStatistics()
    {
        return [
            'total_pages' => self::count(),
            'pages_with_content' => self::hasContent()->count(),
            'pages_without_content' => self::whereNull('terms_description')
                ->orWhere('terms_description', '')
                ->count(),
            'recently_updated' => self::getRecentlyUpdated(7)->count(),
        ];
    }

    /**
     * Initialize default pages
     * 
     * @return void
     */
    public static function initializeDefaultPages()
    {
        $defaultPages = [
            'terms_and_conditions',
            'data_privacy_policy',
            'about_us',
        ];

        foreach ($defaultPages as $page) {
            self::getOrCreateByTitle($page);
        }
    }

    /**
     * Get available page types
     * 
     * @return array
     */
    public static function getPageTypes()
    {
        return [
            'terms_and_conditions' => 'Terms and Conditions',
            'data_privacy_policy' => 'Data Privacy Policy',
            'about_us' => 'About Us',
            'faq' => 'Frequently Asked Questions',
            'contact_us' => 'Contact Us',
            'refund_policy' => 'Refund Policy',
            'shipping_policy' => 'Shipping Policy',
        ];
    }

    /**
     * Duplicate page content
     * 
     * @param string $fromTitle Source page
     * @param string $toTitle Destination page
     * @param int $adminId Admin performing action
     * @return StaticPage
     */
    public static function duplicatePage($fromTitle, $toTitle, $adminId = null)
    {
        $sourcePage = self::getByTitle($fromTitle);
        
        if (!$sourcePage) {
            return null;
        }

        return self::updatePage($toTitle, $sourcePage->terms_description, $adminId);
    }

    /**
     * Search pages by content
     * 
     * @param string $keyword Search keyword
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function searchPages($keyword)
    {
        return self::where('terms_title', 'LIKE', "%{$keyword}%")
            ->orWhere('terms_description', 'LIKE', "%{$keyword}%")
            ->get();
    }

    /**
     * Get word count
     * 
     * @return int
     */
    public function getWordCountAttribute()
    {
        return str_word_count(strip_tags($this->terms_description));
    }

    /**
     * Get character count
     * 
     * @return int
     */
    public function getCharacterCountAttribute()
    {
        return strlen(strip_tags($this->terms_description));
    }
}
