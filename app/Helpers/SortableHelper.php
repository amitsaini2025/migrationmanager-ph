<?php

namespace App\Helpers;

use Illuminate\Http\Request;

class SortableHelper
{
    /**
     * Generate a sortable link
     *
     * @param string $column
     * @param string $title
     * @param array $attributes
     * @return string
     */
    public static function link(string $column, string $title = null, array $attributes = []): string
    {
        $request = request();
        $currentSort = $request->get('sort', '');
        $currentDirection = '';
        
        // Check if this column is currently being sorted
        if (str_starts_with($currentSort, $column)) {
            $currentDirection = str_starts_with($currentSort, '-') ? 'desc' : 'asc';
        }
        
        // Determine next sort direction
        $nextDirection = $currentDirection === 'asc' ? '-' . $column : $column;
        
        // Build query parameters
        $queryParams = $request->query();
        $queryParams['sort'] = $nextDirection;
        
        // Remove page parameter when sorting
        unset($queryParams['page']);
        
        // Build URL
        $url = $request->url() . '?' . http_build_query($queryParams);
        
        // Build HTML attributes
        $htmlAttributes = '';
        foreach ($attributes as $key => $value) {
            $htmlAttributes .= ' ' . $key . '="' . htmlspecialchars($value) . '"';
        }
        
        // Add sort indicator class
        $class = $attributes['class'] ?? '';
        if ($currentDirection) {
            $class .= ' sort-' . $currentDirection;
        }
        if ($class) {
            $htmlAttributes = ' class="' . trim($class) . '"' . $htmlAttributes;
        }
        
        // Use column name as title if not provided
        $displayTitle = $title ?: ucfirst(str_replace('_', ' ', $column));
        
        return '<a href="' . $url . '"' . $htmlAttributes . '>' . $displayTitle . '</a>';
    }

    /**
     * Generate sortable link with icon
     *
     * @param string $column
     * @param string $title
     * @param array $attributes
     * @return string
     */
    public static function linkWithIcon(string $column, string $title = null, array $attributes = []): string
    {
        $request = request();
        $currentSort = $request->get('sort', '');
        $currentDirection = '';
        
        // Check if this column is currently being sorted
        if (str_starts_with($currentSort, $column)) {
            $currentDirection = str_starts_with($currentSort, '-') ? 'desc' : 'asc';
        }
        
        // Determine next sort direction
        $nextDirection = $currentDirection === 'asc' ? '-' . $column : $column;
        
        // Build query parameters
        $queryParams = $request->query();
        $queryParams['sort'] = $nextDirection;
        
        // Remove page parameter when sorting
        unset($queryParams['page']);
        
        // Build URL
        $url = $request->url() . '?' . http_build_query($queryParams);
        
        // Build HTML attributes
        $htmlAttributes = '';
        foreach ($attributes as $key => $value) {
            if ($key !== 'class') {
                $htmlAttributes .= ' ' . $key . '="' . htmlspecialchars($value) . '"';
            }
        }
        
        // Sort indicator icon (Lucide via IconHelper)
        $class = $attributes['class'] ?? '';
        $legacyIcon = 'fa fa-sort';

        if ($currentDirection === 'asc') {
            $legacyIcon = 'fa fa-sort-up';
            $class .= ' sort-asc';
        } elseif ($currentDirection === 'desc') {
            $legacyIcon = 'fa fa-sort-down';
            $class .= ' sort-desc';
        }

        if ($class) {
            $htmlAttributes = ' class="' . trim($class) . '"' . $htmlAttributes;
        }

        // Use column name as title if not provided
        $displayTitle = $title ?: ucfirst(str_replace('_', ' ', $column));

        $iconHtml = IconHelper::fromLegacy($legacyIcon, ['class' => 'sort-icon']);

        return '<a href="' . $url . '"' . $htmlAttributes . '>' . $displayTitle . ' ' . $iconHtml . '</a>';
    }
} 