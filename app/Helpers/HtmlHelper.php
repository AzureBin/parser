<?php

namespace App\Helpers;

use App\Feeds\Utils\ParserCrawler;

class HtmlHelper
{
    private static array $allowed_tags = [
        'span',
        'p',
        'br',
        'b',
        'strong',
        'ol',
        'ul',
        'li',
        'table',
        'thead',
        'tbody',
        'th',
        'tr',
        'td',
    ];

    private static array $single_tags = [
        'br', 'hr', 'img'
    ];

    /**
     * Вырезает теги с javascript кодом, а также теги стилей и гиперссылки.
     * @param string $string
     * @return string
     */
    public static function sanitiseHtml( string $string ): string
    {
        $regexps = [
            '/<script[^>]*?>.*?<\/script>/i',
            '/<noscript[^>]*?>.*?<\/noscript>/i',
            '/<style[^>]*?>.*?<\/style>/i',
            '/<video[^>]*?>.*?<\/video>/i',
            '/<a[^>]*?>.*?<\/a>/i',
            '/<!--.*?-->/s',
            '/<iframe[^>]*?>.*?<\/iframe>/i'
        ];
        return (string)preg_replace( $regexps, '', $string );
    }

    /**
     * Вырезает блочные теги, очищая оставшиеся теги от всех атрибутов.
     * @param string $string
     * @param bool $flag
     * @param array $tags
     * @return string
     */
    public static function cutTags( string $string, bool $flag = true, array $tags = [] ): string
    {
        $string = self::sanitiseHtml( $string );
        $string = self::cutTagsAttributes( $string );

        $string = StringHelper::trim( $string );
        if ( !$flag ) {
            self::$allowed_tags = [];
        }

        if ( !empty( $tags ) ) {
            foreach ( $tags as $tag ) {
                $regexp = '/<(\D+)\s?[^>]*?>/';
                if ( preg_match( $regexp, $tag, $matches ) ) {
                    self::$allowed_tags[] = $matches[ 1 ];
                }
                else {
                    self::$allowed_tags[] = $tag;
                }
            }
        }

        $tags_string = '<' . implode( '><', self::$allowed_tags ) . '>';
        return strip_tags( $string, $tags_string );
    }

    /**
     * Вырезает все атрибуты тегов
     * @param string $string
     * @return string
     */
    public static function cutTagsAttributes( string $string ): string
    {
        return preg_replace( '/(<\w+)([^>]*)(>)/', '$1$3', $string );
    }

    /**
     * Вырезает пустые теги
     * @param string $string
     * @return string
     */
    public static function cutEmptyTags( string $string ): string
    {
        $crawler = new ParserCrawler( $string );
        $children = $crawler->filter( 'body' )->count() ? $crawler->filter( 'body' )->children() : [];
        foreach ( $children as $child ) {
            /** Если текущий узел содержит дочерние узлы, обрабатываем их по отдельности **/
            if ( $child->childElementCount ) {
                foreach ( $child->childNodes as $node ) {
                    if ( !in_array( $node->nodeName, self::$single_tags, true ) ) {
                        $content = $node->ownerDocument->saveHTML( $node );
                        $string = str_replace( $content, self::cutEmptyTags( $content ), $string );
                    }
                }
            }
            else if ( !in_array( $child->nodeName, self::$single_tags, true ) ) {
                $content = $child->ownerDocument->saveHTML( $child );
                if ( !StringHelper::isNotEmpty( $content ) ) {
                    $string = str_replace( $content, '', $string );
                }
            }
        }
        return $string;
    }
}