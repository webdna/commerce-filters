<?php

namespace webdna\commerce\filters\services;

use Craft;
use yii\base\Component;
use craft\elements\Category;
use Illuminate\Support\Collection;
use craft\helpers\UrlHelper;

/**
 * Filters service
 */
class Filters extends Component
{
    public function filter($allProducts=[], $categories=[], $options=[])
    {
        $categories = collect($categories);
        $allProducts = $allProducts->collect();
        $options = collect($options);
        
        $baseUrl = UrlHelper::stripQueryString(Craft::$app->getRequest()->getUrl());
        $queryParams = collect(Craft::$app->getRequest()->getQueryParams());
        //Craft::dd($queryParams);
        
        $results = Category::find()->group($categories->keys()->map(function($item){return explode('.',$item)[0];}))->with(['children'])->collect()->groupBy([function($item){
            return $item->group->handle;
        }, function($item){
            return $item->parent->slug ?? $item->slug;
        }, function($item){
            return $item->slug;
        }]);
        
        $categories = $categories->mapWithKeys(function($item, $key){
            $keys = collect(explode('.', $key));
            if ($keys->count() > 1) {
                $key = $keys->last();
            }
            return [$key => $item];
        });
        //Craft::dd($results);
            
        $selected = collect();
        
        foreach ($results as $key => $result) {
            //Craft::dd($result);
            $params = $queryParams->pull($categories[$key]);
            if (!$params) {
                $params = collect();
            } elseif (is_array($params)) {
                $params = collect($params);
            } else {
                $params = collect(explode(',',$params));
            }
            
            foreach ($result as $k => $item) {
                $r = $item->flatten();
                if ($r->count() > 1) {

                    $category = $r->shift();
                    $handle = $category->group->handle;
                    
                    if ($categories->has($category->slug)) {
                        $handle = $category->slug;
                    }
                    

                    if ($categories->has($handle)) {
                        $field = $categories[$handle];
                        
                        $filteredCategories = $categories->filter(function($item) use ($field){
                            return $item != $field;
                        });
    
                        $r = $r = $r->mapWithKeys(function($el){
                            return [$el->id => $el];
                        });
                        
                        
                        $r = [
                            'title' => $category->title,
                            'category' => $category,
                            'items' => $r->map(function($el) use ($allProducts, $field, $params, $category, $queryParams, $key, $baseUrl, $handle, $options, $filteredCategories, $categories, $selected){
                                
                                if ($this->checkIsAvailable($allProducts, $field, $el)) {
                                    
                                    $available = $this->isAvailable($allProducts, $field, $el, $filteredCategories, $key);
                                    
                                    $active = false;
                                    
                                    if ($options->has('segment') && in_array($handle, $options['segment'])) {
                                        if (UrlHelper::siteUrl($el->uri) == UrlHelper::siteUrl($baseUrl)) {
                                            $active = true;
                                            $url = $el->parent->url;
                                        } else {
                                            $url = $el->url;
                                        }
                                        
                                    } else {
                                        if ($params->contains($el->id)) {
                                            $p = $params->filter(function($item) use ($el){
                                                return $item != $el->id;
                                            });
                                            $active = true;
                                        } else {
                                            $p = $params->merge([$el->id]);
                                        }
                                        
                                        $url = $this->getUrl($options, [$categories[$key] => $p->join(',')]);
                                        
                                    }
                                    
                                    if ($active) {
                                        $selected[] = [
                                            'title' => $el->title,
                                            'id' => $el->id,
                                            'url' => $url,
                                        ];
                                    }
                                        
                                    return [
                                        'available' => $available > 0,
                                        'title' => $el->title,
                                        'id' => $el->id,
                                        'url' => $url,
                                        'group' => $key,
                                        'active' => $active,
                                        'category' => $el,
                                    ];
                                } else {
                                    return null;
                                }
                            })->filter(function($item){return $item != null;})->all(),
                        ];
                        if (count($r['items'])) {
                            $results[$key][$k] = $r;
                        } else {
                            unset($results[$key][$k]);
                        }
                    } else {
                        unset($results[$key][$k]);
                    }
                    
                } else {
                    $category = $r->first();
                    if ($categories->has($category->group->handle)) {
                        $field = $categories[$category->group->handle];
                        
                        $filteredCategories = $categories->filter(function($item) use ($field){
                            return $item != $field;
                        });
                        
                        
                        
                        if ($this->checkIsAvailable($allProducts, $field, $category)) {
                            
                            $available = $this->isAvailable($allProducts, $field, $category, $filteredCategories);

                            $active = false;
                            if ($params->contains($category->id)) {
                                $p = $params->filter(function($item) use ($category){
                                    return $item != $category->id;
                                });
                                $active = true;
                            } else {
                                $p = $params->merge([$category->id]);
                            }
                            
                            $url = $this->getUrl($options, [$categories[$key] => $p->join(',')]);
                            
                            if ($active) {
                                $selected[] = [
                                    'title' => $category->title,
                                    'id' => $category->id,
                                    'url' => $url,
                                ];
                            }
                            
                            $r = [
                                'available' => $available > 0,
                                'title' => $category->title,
                                'id' => $category->id,
                                'url' => $url,
                                'group' => $key,
                                'active' => $active,
                                'category' => $category,
                            ];
                            
                            $results[$key][$k] = $r;
                        } else {
                            unset($results[$key][$k]);
                        }
                    } else {
                        unset($results[$key][$k]);
                    }
                }
            }
        }
        
        //Craft::dd($results);
            
        $sortedResults = collect();
        foreach ($categories as $key => $value) {
            $sortedResults[$key] = $results[$key];
        }
        
        //Craft::dd($sortedResults);
        //Craft::dd($selected);
        
        return [
            'groups' => $sortedResults,
            'selected' => $selected,
        ];

    }
    
    public function queryParams($options=[])
    {
        $baseUrl = UrlHelper::stripQueryString(Craft::$app->getRequest()->getUrl());
        $queryParams = collect(Craft::$app->getRequest()->getQueryParams());
        $params = collect();
        $options = collect($options);
        $variantQuery = ['relatedTo' => ['and']];
        
        if ($options->has('relatedTo')) {
            $params['relatedTo'] = collect(['and', collect(['or'])->merge($options['relatedTo'])->all()]);
        } else {
            $params->put('relatedTo', collect(['and']));
        }
        
        foreach ($queryParams as $key => $value) {
            if (!is_array($value)) {
                $value = explode(',', $value);
            }
            
            if ($options->has('filters') && collect($options['filters'])->contains($key)) {
                $params['relatedTo']->push(collect(['or'])->merge($value)->all());
            }
            
            if ($options->has('variantFilters') && collect($options['variantFilters'])->contains($key)) {
                $variantQuery['relatedTo'][] = collect(['or'])->merge($value)->all();
            }
        }
        
        if (count($variantQuery['relatedTo']) > 1) {
            $params['hasVariant'] = $variantQuery;
        }
        
        if (count($params['relatedTo']) == 1) {
            unset($params['relatedTo']);
        }
        
            
        //Craft::dd($params);
            
        return $params->all();
    }
    
    private function getUrl($options, $params=[], $url=null)
    {
        $baseUrl = UrlHelper::stripQueryString(Craft::$app->getRequest()->getUrl());
        $queryParams = collect(Craft::$app->getRequest()->getQueryParams());
            
        $qp = $queryParams->merge($params)->sortKeys()->filter(function($item){return $item != '';});
        
        $qp = $qp->filter(function($item, $key) use ($options){
            if ($options->has('filters')) {
                return collect($options['filters'])->contains($key);
            }
            return true;
        })->all();
        
        if (isset($options['perPage'])) {
            $qp['perPage'] = $options['perPage'];
        }
        
        if (!$url) {
            //Craft::dd($qp);
            $url = '?';
        }
        
        return $url.UrlHelper::buildQuery($qp);
    }
    
    private function checkIsAvailable($products, $field, $category)
    {
        $available = $products->filter(function($item) use ($field, $category){ 
            
            if ($item->{"$field"}) {
                return $item->{"$field"}->ids()->contains($category->id);
            } else {
                
                foreach ($item->variants as $variant) {
                    if ($variant->{"$field"}->ids()->contains($category->id)) {
                        return true;
                    }
                }
                return false;
            }
        });
        
        return $available->count() > 0;
    }
    
    private function isAvailable($products, $field, $category, $filteredCategories, $key='')
    {
        $queryParams = collect(Craft::$app->getRequest()->getQueryParams());
            
        $available = $products->filter(function($item) use ($field, $category){
            // check if product has category
            if ($item->{"$field"}) {
                return $item->{"$field"}->ids()->contains($category->id);
            } else {
                
                foreach ($item->variants as $variant) {
                    if ($variant->{"$field"}->ids()->contains($category->id)) {
                        return true;
                    }
                }
                return false;
            }
        });
        
        $available = $available->filter(function($item) use ($filteredCategories, $queryParams, $key, $category){
            
            $return = collect();
            
            foreach ($filteredCategories as $fc) {
                $return->put($fc, true);
                
                 if ($queryParams->has($fc)) {
                    if ($item->{"$fc"}) {
                       if ($item->{"$fc"}->ids()->intersect(explode(',', $queryParams[$fc]))->count() === 0) {
                            $return[$fc] = false;
                        }
                    } else {
                        $r = false;
                        foreach ($item->variants as $variant) {
                            if ($variant->{"$fc"}->ids()->intersect(explode(',', $queryParams[$fc]))->count() > 0) {
                                $r = true;
                            }
                        }
                        $return[$fc] = $r;
                    }
                }
            }
            
            return !$return->contains(false);
        });
        
        
        return $available->count();
    }
}
