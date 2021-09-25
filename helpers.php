<?php
function remove_url_query($url, $key) {
    $url = preg_replace('/(?:&|(\?))' . $key . '=[^&]*(?(1)&|)?/i', "$1", $url);
    $url = rtrim($url, '?');
    $url = rtrim($url, '&');
    return $url;
}

function generate_search_params(array $searchFields)
{
    $search = [];
    foreach($searchFields as $field => $type) {
        if ($field == 'price' && !empty($_REQUEST[$field . '_min']) && !empty($_REQUEST[$field . '_max'])) {
            $search[':' . $field] = [
                'type' => $type, 
                'value_min' => filter_var($_REQUEST[$field . '_min'], FILTER_SANITIZE_NUMBER_FLOAT),
                'value_max' => filter_var($_REQUEST[$field . '_max'], FILTER_SANITIZE_NUMBER_FLOAT)
            ];
            continue;
        }

        if (!empty($_REQUEST[$field])) {
            $search[':' . ($field == 'property_type' ? 'title' : $field)] = ['type' => $type, 'value' => filter_var($_REQUEST[$field], FILTER_SANITIZE_STRING)];
        }
    }

    return $search;
}