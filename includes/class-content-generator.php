<?php
if (! defined('ABSPATH')) {
    exit;
}

class BRW_Content_Generator
{
    public static function generate(int $product_id, int $rating, array $settings): string
    {
        $product = function_exists('wc_get_product') ? wc_get_product($product_id) : null;
        $language = $settings['language'] ?? 'en_US';

        // If manual phrases are provided, randomly pick one of them first
        if (! empty($settings['manual_reviews'])) {
            $list = array_filter(array_map('trim', preg_split('/\r?\n/', (string) $settings['manual_reviews'])));
            if (! empty($list)) {
                return $list[array_rand($list)];
            }
        }

        // Try AI if configured
        $response = self::maybe_generate_ai($product, $rating, $settings);
        if (is_string($response) && $response) {
            return $response;
        }

        // Fallback: template-based
        $tpl = BRW_Template_Manager::get_template_for_product($product_id, $language);
        $name = $product ? $product->get_name() : __('this product', 'bulk-review-wizard');
        $open = self::pick(self::sentiment_bucket($tpl['openers'] ?? [], $rating));
        $feature = self::pick($tpl['features'] ?? ['quality', 'design', 'value']);
        $closer = self::pick(self::sentiment_bucket($tpl['closers'] ?? [], $rating));
        $len = $settings['length'] ?? 'medium';
        $extra = ['Shipping was fast.', 'Easy to use.', 'Great support.', 'Well packaged.', 'Looks great.'];
        $sentences = [sprintf('%s %s.', $open, $name)];
        $sentences[] = sprintf('The %s is %s.', $name, $feature);
        if ('long' === $len) {
            $sentences[] = self::pick($extra);
        }
        $sentences[] = $closer;
        return trim(implode(' ', $sentences));
    }

    private static function maybe_generate_ai($product, int $rating, array $settings)
    {
        $options = get_option(BRW_Settings::OPTION, []);
        $api_key = $options['api_key'] ?? '';
        $provider = $options['provider'] ?? '';
        $model = $options['model'] ?? '';
        $base_url = $options['base_url'] ?? '';
        if (empty($api_key) || empty($model)) {
            return false;
        }

        // Simple rate limit using transient
        $limit = (int) ($options['rate_limit'] ?? 60);
        $key = 'brw_ai_rate_' . (int) floor(time() / 60);
        $count = (int) get_transient($key);
        if ($count >= max(1, $limit)) {
            return false;
        }

        $title = $product ? $product->get_name() : 'Product';
        $language = $settings['language'] ?? 'en_US';
        $len = $settings['length'] ?? 'medium';
        $style = ($rating >= 4 ? 'positive' : ($rating == 3 ? 'neutral' : 'negative'));
        $prompt = sprintf(
            "Write a %s-length %s product review in locale %s for '%s'. Mention 1-2 features. Avoid repeating. Keep it natural.",
            $len,
            $style,
            $language,
            $title
        );

        $temperature = isset($options['temperature']) ? (float) $options['temperature'] : 0.7;
        $max_tokens = isset($options['max_tokens']) ? (int) $options['max_tokens'] : 150;

        $headers = ['Content-Type' => 'application/json'];
        $body = [];
        $url = '';

        switch ($provider) {
            case 'google':
                // Gemini (generative language) v1beta style
                $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($api_key);
                $body = [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]],
                    ],
                    'generationConfig' => [
                        'temperature' => $temperature,
                        'maxOutputTokens' => $max_tokens,
                    ],
                ];
                break;
            case 'anthropic':
                $url = 'https://api.anthropic.com/v1/messages';
                $headers['x-api-key'] = $api_key;
                $headers['anthropic-version'] = '2023-06-01';
                $body = [
                    'model' => $model,
                    'max_tokens' => $max_tokens,
                    'temperature' => $temperature,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ];
                break;
            case 'openrouter':
                $url = 'https://openrouter.ai/api/v1/chat/completions';
                $headers['Authorization'] = 'Bearer ' . $api_key;
                $body = [
                    'model' => $model,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'temperature' => $temperature,
                    'max_tokens' => $max_tokens,
                ];
                break;
            case 'openai':
            default:
                // OpenAI or compatible (DeepSeek, Mistral-server, LocalAI, etc.)
                $endpoint = rtrim($base_url ?: 'https://api.openai.com/v1', '/');
                $url = $endpoint . '/chat/completions';
                $headers['Authorization'] = 'Bearer ' . $api_key;
                $body = [
                    'model' => $model,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'temperature' => $temperature,
                    'max_tokens' => $max_tokens,
                ];
                break;
        }

        $req = [
            'headers' => $headers,
            'body' => wp_json_encode($body),
            'timeout' => 20,
        ];
        $resp = wp_remote_post($url, $req);
        if (is_wp_error($resp)) {
            return false;
        }
        $code = wp_remote_retrieve_response_code($resp);
        if ($code < 200 || $code >= 300) {
            return false;
        }
        $data = json_decode(wp_remote_retrieve_body($resp), true);

        $text = '';
        if ($provider === 'google') {
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        } elseif ($provider === 'anthropic') {
            $text = $data['content'][0]['text'] ?? '';
        } else {
            $text = $data['choices'][0]['message']['content'] ?? ($data['choices'][0]['text'] ?? '');
        }

        if ($text) {
            set_transient($key, $count + 1, MINUTE_IN_SECONDS + 5);
            return trim($text);
        }
        return false;
    }

    private static function pick(array $arr)
    {
        return $arr ? $arr[array_rand($arr)] : '';
    }
    private static function sentiment_bucket(array $arr, int $rating): array
    {
        if (empty($arr)) {
            return $arr;
        }
        if ($rating >= 4) {
            return $arr;
        }
        if ($rating == 3) {
            return $arr;
        }
        return $arr; // placeholder for advanced sentiment templates per rating
    }
}
