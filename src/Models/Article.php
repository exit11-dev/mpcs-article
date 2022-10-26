<?php

namespace Mpcs\Article\Models;

use Mpcs\Article\Facades\Article as Facade;
use Illuminate\Database\Eloquent\Model;
use Mpcs\Core\Facades\Core;
use Mpcs\Core\Traits\ModelTrait;
use Cviebrock\EloquentSluggable\Sluggable;
use Cviebrock\EloquentTaggable\Taggable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

use Illuminate\Support\Str;

class Article extends Model
{
    use SoftDeletes, Sluggable, Taggable, ModelTrait;

    protected $table = 'articles';
    protected $dates = ['created_at', 'updated_at', 'deleted_at', 'released_at'];
    protected $guarded = ['id'];
    protected static $m_params = [
        'default_load_relations' => ['articleCategory', 'articleFiles', 'tags', 'user'],
        'column_maps' => [
            // date : {컬럼명}
            'from' => 'released_at',
            'to' => 'released_at',
        ]
    ];
    // $sortable 정의시 정렬기능을 제공할 필드는 필수 기입
    public $sortable = ['id', 'title', 'view_count', 'released_at'];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i',
        'updated_at' => 'datetime:Y-m-d H:i',
        'deleted_at' => 'datetime:Y-m-d H:i',
        'released_at' => 'datetime:Y-m-d H:i',
        'status_released' => 'boolean',
    ];

    protected $appends = [
        'status_released',
        'thumb_image_url',
        'small_image_url',
        'medium_image_url',
        'large_image_url',
        'image_aspect_ratio',
        'preview_text',
    ];

    private $uploadDisk;
    private $imageRootDir;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->uploadDisk = Storage::disk('upload');
        $this->imageRootDir = 'articles';
    }

    /**
     * boot
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();
        static::setMemberParams(self::$m_params);
    }


    /**
     * articleCategories
     *
     * @return void
     */
    public function articleCategory()
    {
        return $this->belongsTo(ArticleCategory::class, 'article_category_id');
    }

    /**
     * articleFiles
     *
     * @return void
     */
    public function articleFiles()
    {
        return $this->hasMany(ArticleFile::class, 'article_id');
    }

    /**
     * user
     *
     * @return void
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User', 'user_id');
    }


    /**
     * getPreviewTextAttribute
     *
     * @return void
     */
    public function getPreviewTextAttribute()
    {
        $preview_text = $this->summary ?? strip_tags($this->html);
        return Str::limit(strip_tags($preview_text), 100, ' ...');
    }

    /**
     * getStatusReleasedAttribute
     *
     * @return void
     */
    public function getStatusReleasedAttribute()
    {
        $now = Carbon::now()->format('Y-m-d H:i:s');
        $released = $this->attributes['released_at'];
        return ($released && $released <= $now);
    }

    /**
     * getViewCountAttribute
     *
     * @return void
     */
    public function getViewCountAttribute()
    {
        return $this->attributes['view_count'] ?? 0;
    }

    /**
     * setReleasedAtAttribute
     *
     * @param  mixed $date
     * @return void
     */
    public function setReleasedAtAttribute($date)
    {
        $this->attributes['released_at'] = empty($date) ? null : Carbon::parse($date);
    }

    /**
     * scopeReleased
     *
     * @param  mixed $query
     * @return void
     */
    public function scopeReleased($query)
    {
        $now = Carbon::now()->format('Y-m-d H:i:s');
        return $query->where('released_at', '<=', $now);
    }

    /**
     * getUploadDiskAttribute
     *
     * @return void
     */
    public function getUploadDiskAttribute()
    {
        return $this->uploadDisk;
    }

    /**
     * getRootDirAttribute
     *
     * @return void
     */
    public function getImageRootDirAttribute()
    {
        return $this->imageRootDir;
    }

    /**
     * getFileUrlAttribute
     *
     * @return void
     */
    public function getImageFileUrlAttribute()
    {
        if ($this->thumbnail) {
            return $this->upload_disk->url($this->image_root_dir . '/' . $this->thumbnail);
        }
        return Facade::noImage();
    }

    /**
     * getThumbImageUrlAttribute
     *
     * @return void
     */
    public function getThumbImageUrlAttribute()
    {
        if ($this->thumbnail) {
            return $this->upload_disk->url($this->image_root_dir . '/thumbnails/thumb_' . $this->thumbnail);
        }
        return Facade::noImage();
    }

    /**
     * getSmallImageUrlAttribute
     *
     * @return void
     */
    public function getSmallImageUrlAttribute()
    {
        if ($this->thumbnail) {
            return $this->upload_disk->url($this->image_root_dir . '/thumbnails/small_' . $this->thumbnail);
        }
        return Facade::noImage();
    }

    /**
     * getMediumImageUrlAttribute
     *
     * @return void
     */
    public function getMediumImageUrlAttribute()
    {
        if ($this->thumbnail) {
            return $this->upload_disk->url($this->image_root_dir . '/thumbnails/medium_' . $this->thumbnail);
        }
        return Facade::noImage();
    }

    /**
     * getLargeImageUrlAttribute
     *
     * @return void
     */
    public function getLargeImageUrlAttribute()
    {
        if ($this->thumbnail) {
            return $this->upload_disk->url($this->image_root_dir . '/thumbnails/large_' . $this->thumbnail);
        }
        return Facade::noImage();
    }

    /**
     * getImageAspectRatioAttribute
     *
     * @return void
     */
    public function getImageAspectRatioAttribute()
    {
        if ($this->thumbnail) {
            $image = $this->upload_disk->get($this->image_root_dir . '/' . $this->thumbnail);
            if ($image) {
                $width = Image::make($image)->width();
                $height = Image::make($image)->height();
                $aspectRatio = ($height / $width) * 100;
                return $aspectRatio;
            }
        }
        return 0;
    }

    /**
     * sluggable
     *
     * @return void
     */
    public function sluggable(): array
    {
        return [
            'slug' => [
                'source'     => 'title',
                'method' => function ($string, $separator) {
                    return preg_replace('/[^0-9a-zA-Z가-힣ㄱ-ㅎㅏ]+/i', $separator, $string);
                },
                'onUpdate'  => true
            ]
        ];
    }

    /**
     * scopeCustom
     *
     * @return void
     */
    public function scopeCustom($query, $params)
    {
        if (isset($params['__released'])) {
            $released = $params['__released'];
            if ($released === "true") {
                $now = Carbon::now()->format('Y-m-d H:i:s');
                $query->where('released_at', '<=', $now);
            }
        }
    }
}
