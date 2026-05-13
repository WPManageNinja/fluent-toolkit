<?php

namespace FluentToolkit\Classes;

class UnifiedUiHandler
{

    protected $apps = [];


    public function register()
    {

        if(!defined('FLUENT_UNIFIED_UIX')) {
           // return;
        }

        $contactSubMenu = [
            'contacts' => [
                'title' => __('All Contacts', 'fluent-toolkit'),
                'url'   => admin_url('admin.php?page=fluentcrm-admin#/subscribers'),
            ],
            'tags'     => [
                'title' => __('Tags', 'fluent-toolkit'),
                'url'   => admin_url('admin.php?page=fluentcrm-admin#/contact-groups/tags'),
            ],
            'lists'    => [
                'title' => __('Lists', 'fluent-toolkit'),
                'url'   => admin_url('admin.php?page=fluentcrm-admin#/contact-groups/lists'),
            ],
            'segments' => [
                'title' => __('Segments', 'fluent-toolkit'),
                'url'   => admin_url('admin.php?page=fluentcrm-admin#/contact-groups/dynamic-segments'),
            ]
        ];

        $emailsSubMenu = [
            'campaigns'           => [
                'title' => __('Campaigns', 'fluent-toolkit'),
                'url'   => admin_url('admin.php?page=fluentcrm-admin#/email/campaigns'),
            ],
            'recurring-campaigns' => [
                'title' => __('Recurring Campaigns', 'fluent-toolkit'),
                'url'   => admin_url('admin.php?page=fluentcrm-admin#/email/recurring-campaigns'),
            ],
            'sequences'           => [
                'title' => __('Sequences', 'fluent-toolkit'),
                'url'   => admin_url('admin.php?page=fluentcrm-admin#/email/sequences'),
            ],
            'templates'           => [
                'title' => __('Templates', 'fluent-toolkit'),
                'url'   => admin_url('admin.php?page=fluentcrm-admin#/email/templates'),
            ],
            'patterns'            => [
                'title' => __('Patterns', 'fluent-toolkit'),
                'url'   => admin_url('admin.php?page=fluentcrm-admin#/email/patterns'),
            ],
            'all-emails'          => [
                'title' => __('All Email Logs', 'fluent-toolkit'),
                'url'   => admin_url('admin.php?page=fluentcrm-admin#/email/all-emails'),
            ]
        ];

        $apps = [
            'fluentcrm-admin' => [
                'disabled' => !defined('FLUENTCRM'),
                'title'    => 'CRM',
                'icon'     => FLUENT_BETA_TESTING_PLUGIN_URL . 'dist/images/fluentcrm_icon.svg',
                'logo'     => FLUENT_BETA_TESTING_PLUGIN_URL . 'dist/images/fluentcrm-logo.svg',
                'items'    => [
                    'overview'    => [
                        'title'    => __('Overview', 'fluent-toolkit'),
                        'url'      => admin_url('admin.php?page=fluentcrm-admin#/'),
                        'icon_svg' => '<svg viewBox="0 0 24 24" width="128" height="128" color="currentColor" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 1.75C12.4925 1.75 12.9709 1.91601 13.3574 2.22101L21.9521 9.00702C22.4559 9.40502 22.75 10.011 22.75 10.653C22.7497 11.779 21.8629 12.694 20.75 12.745V15.5C20.75 16.893 20.7519 18.013 20.6338 18.892C20.5128 19.792 20.2533 20.549 19.6514 21.151C19.0495 21.753 18.2917 22.013 17.3916 22.134C16.6251 22.237 15.6746 22.247 14.5195 22.248C14.513 22.248 14.5065 22.25 14.5 22.25C14.4935 22.25 14.487 22.249 14.4805 22.249C14.3241 22.249 14.1639 22.25 14 22.25H10C9.83574 22.25 9.67528 22.249 9.51855 22.249C9.51238 22.249 9.50621 22.25 9.5 22.25C9.49313 22.25 9.48632 22.248 9.47949 22.248C8.32485 22.247 7.37468 22.237 6.6084 22.134C5.70829 22.013 4.95055 21.753 4.34863 21.151C3.74672 20.549 3.48723 19.792 3.36621 18.892C3.24812 18.013 3.25 16.893 3.25 15.5V12.745C2.13709 12.694 1.25025 11.779 1.25 10.653C1.25 10.011 1.54409 9.40502 2.04785 9.00702L10.6426 2.22101C11.0291 1.91601 11.5075 1.75 12 1.75ZM12 15.25C11.5189 15.25 11.2081 15.251 10.9727 15.272C10.7476 15.293 10.6659 15.327 10.625 15.351C10.511 15.416 10.4164 15.511 10.3506 15.625C10.327 15.666 10.2929 15.748 10.2725 15.973C10.2511 16.208 10.25 16.519 10.25 17V20.75H13.75V17C13.75 16.519 13.7489 16.208 13.7275 15.973C13.7173 15.86 13.7036 15.783 13.6895 15.729L13.6494 15.625C13.6001 15.54 13.5347 15.465 13.457 15.405L13.375 15.351C13.3341 15.327 13.2524 15.293 13.0273 15.272C12.7919 15.251 12.4811 15.25 12 15.25ZM12 3.25C11.8448 3.25 11.6941 3.30201 11.5723 3.39801L2.97754 10.185C2.83409 10.298 2.75 10.471 2.75 10.653C2.75026 10.983 3.01726 11.25 3.34668 11.25H4L4.07715 11.254C4.45512 11.293 4.75 11.612 4.75 12V15.5C4.75 16.935 4.75202 17.936 4.85352 18.691C4.95217 19.425 5.13242 19.814 5.40918 20.091C5.68594 20.368 6.07482 20.548 6.80859 20.646C7.32231 20.716 7.94968 20.736 8.75 20.744V17C8.75 16.546 8.74942 16.156 8.77832 15.837C8.80817 15.508 8.87447 15.182 9.05176 14.875C9.24921 14.533 9.53306 14.249 9.875 14.052C10.1821 13.874 10.5079 13.808 10.8369 13.778C11.1558 13.749 11.5465 13.75 12 13.75C12.4535 13.75 12.8442 13.749 13.1631 13.778C13.4921 13.808 13.8179 13.874 14.125 14.052L14.251 14.13C14.5369 14.321 14.7756 14.576 14.9482 14.875L15.0098 14.991C15.1416 15.264 15.1956 15.549 15.2217 15.837C15.2506 16.156 15.25 16.546 15.25 17V20.744C16.0503 20.736 16.6777 20.716 17.1914 20.646C17.9252 20.548 18.3141 20.368 18.5908 20.091C18.8676 19.814 19.0478 19.425 19.1465 18.691C19.248 17.936 19.25 16.935 19.25 15.5V12C19.25 11.586 19.5858 11.25 20 11.25H20.6533C20.9827 11.25 21.2497 10.983 21.25 10.653C21.25 10.471 21.1659 10.298 21.0225 10.185L12.4277 3.39801C12.3059 3.30201 12.1552 3.25 12 3.25Z" fill="currentColor" /></svg>'
                    ],
                    'contacts'    => [
                        'title'    => __('Contacts', 'fluent-toolkit'),
                        'url'      => admin_url('admin.php?page=fluentcrm-admin#/subscribers'),
                        'icon_svg' => '<svg viewBox="0 0 24 24" width="128" height="128" color="currentColor" fill="none"><path d="M5.5 21V20.25C5.5 16.7982 8.29822 14 11.75 14C15.2018 14 18 16.7982 18 20.25V21H19.5V20.25C19.5 17.0526 17.5628 14.3093 14.7988 13.125C16.4209 12.1084 17.5 10.3055 17.5 8.25C17.5 5.07436 14.9256 2.5 11.75 2.5C8.57436 2.5 6 5.07436 6 8.25C6 10.3052 7.07849 12.1083 8.7002 13.125C5.93662 14.3095 4 17.0529 4 20.25V21H5.5ZM11.75 12.5C9.40279 12.5 7.5 10.5972 7.5 8.25C7.5 5.90279 9.40279 4 11.75 4C14.0972 4 16 5.90279 16 8.25C16 10.5972 14.0972 12.5 11.75 12.5Z" fill="currentColor" /></svg>',
                        'sub_menu' => $contactSubMenu
                    ],
                    'emails'      => [
                        'title'    => __('Emails', 'fluent-toolkit'),
                        'url'      => admin_url('admin.php?page=fluentcrm-admin#/email/campaigns'),
                        'icon_svg' => '<svg viewBox="0 0 24 24" width="128" height="128" color="currentColor" fill="none"><defs /><path fill="currentColor" d="M14.92,2.787 L14.978,2.788 C16.503,2.827 17.73,2.857 18.713,3.029 C19.743,3.208 20.58,3.552 21.286,4.261 C21.99,4.968 22.332,5.793 22.508,6.805 C22.676,7.77 22.701,8.967 22.733,10.45 L22.734,10.508 C22.755,11.505 22.755,12.495 22.734,13.492 L22.733,13.55 C22.701,15.033 22.676,16.23 22.508,17.195 C22.332,18.207 21.99,19.032 21.286,19.739 C20.58,20.448 19.743,20.792 18.713,20.971 C17.73,21.143 16.503,21.174 14.978,21.212 L14.92,21.213 C12.967,21.262 11.033,21.262 9.08,21.213 L9.022,21.212 C7.497,21.174 6.27,21.143 5.287,20.971 C4.257,20.792 3.42,20.448 2.714,19.739 C2.01,19.032 1.668,18.207 1.492,17.195 C1.324,16.23 1.299,15.033 1.267,13.55 L1.266,13.492 C1.245,12.495 1.245,11.505 1.266,10.508 L1.267,10.45 L1.267,10.45 C1.299,8.967 1.324,7.77 1.492,6.805 C1.668,5.793 2.01,4.968 2.714,4.261 C3.42,3.552 4.257,3.208 5.287,3.029 C6.27,2.857 7.497,2.827 9.022,2.788 L9.08,2.787 C11.033,2.738 12.967,2.738 14.92,2.787 Z M2.921,7.38 C2.818,8.173 2.795,9.174 2.766,10.54 C2.745,11.515 2.745,12.485 2.766,13.46 C2.799,15.015 2.824,16.098 2.97,16.938 C3.109,17.742 3.349,18.251 3.776,18.68 C4.201,19.106 4.717,19.35 5.544,19.494 C6.405,19.644 7.521,19.674 9.118,19.714 C11.046,19.762 12.954,19.762 14.882,19.714 C16.479,19.674 17.595,19.644 18.456,19.494 C19.284,19.35 19.799,19.106 20.224,18.68 C20.651,18.251 20.891,17.742 21.03,16.938 C21.176,16.098 21.201,15.015 21.234,13.46 C21.255,12.485 21.255,11.515 21.234,10.54 C21.205,9.175 21.182,8.173 21.079,7.381 L15.457,10.566 C14.164,11.299 13.113,11.746 12.001,11.746 C10.888,11.746 9.837,11.299 8.544,10.566 Z M9.118,4.286 C7.521,4.326 6.405,4.356 5.544,4.506 C4.717,4.65 4.201,4.894 3.776,5.32 C3.603,5.494 3.461,5.681 3.343,5.895 L9.283,9.261 C10.539,9.972 11.3,10.246 12.001,10.246 C12.701,10.246 13.462,9.972 14.718,9.261 L20.657,5.896 C20.539,5.681 20.397,5.494 20.224,5.32 C19.799,4.894 19.284,4.65 18.456,4.506 C17.595,4.356 16.479,4.326 14.882,4.286 C12.954,4.238 11.046,4.238 9.118,4.286 Z" /></svg>',
                        'sub_menu' => $emailsSubMenu
                    ],
                    'forms'       => [
                        'title'    => __('Forms', 'fluent-toolkit'),
                        'url'      => admin_url('admin.php?page=fluentcrm-admin#/forms'),
                        'icon_svg' => '<svg viewBox="0 0 24 24" width="128" height="128" color="currentColor" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M4 8C3.45 8 3 8.45 3 9C3 9.55 3.45 10 4 10H20C20.55 10 21 9.55 21 9C21 8.45 20.55 8 20 8H4ZM4 14C3.45 14 3 14.45 3 15C3 15.55 3.45 16 4 16H14C14.55 16 15 15.55 15 15C15 14.45 14.55 14 14 14H4Z" fill="currentColor" /></svg>'
                    ],
                    'automations' => [
                        'title'    => __('Automations', 'fluent-toolkit'),
                        'url'      => admin_url('admin.php?page=fluentcrm-admin#/funnels'),
                        'icon_svg' => '<svg viewBox="0 0 24 24" width="128" height="128" color="currentColor" fill="none"><path d="M3.75 20.25H17.75V21.75H2.25V6.25H3.75V20.25ZM21.75 17.75H6.25V2.25H21.75V17.75ZM7.75 16.25H20.25V3.75H7.75V16.25ZM15.04 6.79297L13.4014 9.25098H17.4014L14.208 14.041L12.96 13.209L14.5986 10.751H10.5986L13.792 5.96094L15.04 6.79297Z" fill="currentColor" /></svg>'
                    ],
                    'reports'     => [
                        'title'    => __('Reports', 'fluent-toolkit'),
                        'url'      => admin_url('admin.php?page=fluentcrm-admin#/reports'),
                        'icon_svg' => '<svg viewBox="0 0 24 24" width="128" height="128" color="currentColor" fill="none"><defs /><path fill="currentColor" d="M4.25,3 L4.25,14 C4.25,15.671 4.252,16.849 4.371,17.74 C4.48,18.549 4.675,19.025 4.984,19.369 C5.16,19.016 5.351,18.582 5.571,18.085 L5.571,18.085 L5.704,17.784 C6.034,17.038 6.416,16.197 6.857,15.412 C7.296,14.631 7.817,13.864 8.445,13.285 C9.079,12.7 9.865,12.269 10.806,12.269 C12.091,12.269 12.897,13.093 13.463,13.672 L13.51,13.72 C14.15,14.374 14.533,14.722 15.114,14.722 C15.671,14.722 16.06,14.503 16.418,14.111 C16.797,13.696 17.101,13.133 17.469,12.452 L17.509,12.379 L17.515,12.367 C18.222,11.059 19.2,9.25 21.5,9.25 C21.914,9.25 22.25,9.586 22.25,10 C22.25,10.414 21.914,10.75 21.5,10.75 C20.194,10.75 19.6,11.666 18.828,13.092 L18.757,13.224 C18.42,13.849 18.034,14.566 17.526,15.123 C16.947,15.756 16.178,16.222 15.114,16.222 C13.856,16.222 13.059,15.406 12.501,14.834 L12.439,14.77 C11.806,14.124 11.41,13.769 10.806,13.769 C10.356,13.769 9.917,13.967 9.462,14.387 C9.001,14.813 8.571,15.425 8.165,16.147 C7.761,16.864 7.405,17.647 7.075,18.392 C7.032,18.489 6.989,18.587 6.946,18.684 C6.73,19.175 6.519,19.652 6.318,20.054 C6.453,20.082 6.6,20.107 6.759,20.128 C7.651,20.248 8.829,20.25 10.5,20.25 L21.5,20.25 C21.914,20.25 22.25,20.586 22.25,21 C22.25,21.414 21.914,21.75 21.5,21.75 L10.444,21.75 C8.842,21.75 7.563,21.75 6.56,21.615 C5.523,21.476 4.67,21.18 3.995,20.505 C3.32,19.83 3.024,18.977 2.885,17.94 C2.75,16.937 2.75,15.658 2.75,14.056 L2.75,3 C2.75,2.586 3.086,2.25 3.5,2.25 C3.914,2.25 4.25,2.586 4.25,3 Z M12.25,7 C12.25,7.414 11.914,7.75 11.5,7.75 L7.5,7.75 C7.086,7.75 6.75,7.414 6.75,7 C6.75,6.586 7.086,6.25 7.5,6.25 L11.5,6.25 C11.914,6.25 12.25,6.586 12.25,7 Z M8.5,4.75 L7.5,4.75 C7.086,4.75 6.75,4.414 6.75,4 C6.75,3.586 7.086,3.25 7.5,3.25 L8.5,3.25 C8.914,3.25 9.25,3.586 9.25,4 C9.25,4.414 8.914,4.75 8.5,4.75 Z" /></svg>'
                    ]
                ]
            ],
            'fluent-cart'     => [
                'disabled' => !defined('FLUENTCART_VERSION'),
                'title'    => 'Commerce',
                'icon'     => FLUENT_BETA_TESTING_PLUGIN_URL . 'dist/images/fluentcart_icon.svg',
                'logo'     => FLUENT_BETA_TESTING_PLUGIN_URL . 'dist/images/fluentcart_logo.svg',
                'items'    => [
                    'overview'      => [
                        'title'    => __('Overview', 'fluent-toolkit'),
                        'url'      => admin_url('admin.php?page=fluent-cart#/'),
                        'icon_svg' => '<svg viewBox="0 0 24 24" width="128" height="128" color="currentColor" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 1.75C12.4925 1.75 12.9709 1.91601 13.3574 2.22101L21.9521 9.00702C22.4559 9.40502 22.75 10.011 22.75 10.653C22.7497 11.779 21.8629 12.694 20.75 12.745V15.5C20.75 16.893 20.7519 18.013 20.6338 18.892C20.5128 19.792 20.2533 20.549 19.6514 21.151C19.0495 21.753 18.2917 22.013 17.3916 22.134C16.6251 22.237 15.6746 22.247 14.5195 22.248C14.513 22.248 14.5065 22.25 14.5 22.25C14.4935 22.25 14.487 22.249 14.4805 22.249C14.3241 22.249 14.1639 22.25 14 22.25H10C9.83574 22.25 9.67528 22.249 9.51855 22.249C9.51238 22.249 9.50621 22.25 9.5 22.25C9.49313 22.25 9.48632 22.248 9.47949 22.248C8.32485 22.247 7.37468 22.237 6.6084 22.134C5.70829 22.013 4.95055 21.753 4.34863 21.151C3.74672 20.549 3.48723 19.792 3.36621 18.892C3.24812 18.013 3.25 16.893 3.25 15.5V12.745C2.13709 12.694 1.25025 11.779 1.25 10.653C1.25 10.011 1.54409 9.40502 2.04785 9.00702L10.6426 2.22101C11.0291 1.91601 11.5075 1.75 12 1.75ZM12 15.25C11.5189 15.25 11.2081 15.251 10.9727 15.272C10.7476 15.293 10.6659 15.327 10.625 15.351C10.511 15.416 10.4164 15.511 10.3506 15.625C10.327 15.666 10.2929 15.748 10.2725 15.973C10.2511 16.208 10.25 16.519 10.25 17V20.75H13.75V17C13.75 16.519 13.7489 16.208 13.7275 15.973C13.7173 15.86 13.7036 15.783 13.6895 15.729L13.6494 15.625C13.6001 15.54 13.5347 15.465 13.457 15.405L13.375 15.351C13.3341 15.327 13.2524 15.293 13.0273 15.272C12.7919 15.251 12.4811 15.25 12 15.25ZM12 3.25C11.8448 3.25 11.6941 3.30201 11.5723 3.39801L2.97754 10.185C2.83409 10.298 2.75 10.471 2.75 10.653C2.75026 10.983 3.01726 11.25 3.34668 11.25H4L4.07715 11.254C4.45512 11.293 4.75 11.612 4.75 12V15.5C4.75 16.935 4.75202 17.936 4.85352 18.691C4.95217 19.425 5.13242 19.814 5.40918 20.091C5.68594 20.368 6.07482 20.548 6.80859 20.646C7.32231 20.716 7.94968 20.736 8.75 20.744V17C8.75 16.546 8.74942 16.156 8.77832 15.837C8.80817 15.508 8.87447 15.182 9.05176 14.875C9.24921 14.533 9.53306 14.249 9.875 14.052C10.1821 13.874 10.5079 13.808 10.8369 13.778C11.1558 13.749 11.5465 13.75 12 13.75C12.4535 13.75 12.8442 13.749 13.1631 13.778C13.4921 13.808 13.8179 13.874 14.125 14.052L14.251 14.13C14.5369 14.321 14.7756 14.576 14.9482 14.875L15.0098 14.991C15.1416 15.264 15.1956 15.549 15.2217 15.837C15.2506 16.156 15.25 16.546 15.25 17V20.744C16.0503 20.736 16.6777 20.716 17.1914 20.646C17.9252 20.548 18.3141 20.368 18.5908 20.091C18.8676 19.814 19.0478 19.425 19.1465 18.691C19.248 17.936 19.25 16.935 19.25 15.5V12C19.25 11.586 19.5858 11.25 20 11.25H20.6533C20.9827 11.25 21.2497 10.983 21.25 10.653C21.25 10.471 21.1659 10.298 21.0225 10.185L12.4277 3.39801C12.3059 3.30201 12.1552 3.25 12 3.25Z" fill="currentColor" /></svg>'
                    ],
                    'orders'        => [
                        'title'    => __('Orders', 'fluent-toolkit'),
                        'url'      => admin_url('admin.php?page=fluent-cart#/orders'),
                        'icon_svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" class=""><path d="M6.6665 13.3333L13.9333 12.7277C16.207 12.5383 16.7174 12.0417 16.9694 9.77408L17.4998 5" stroke="currentColor" stroke-width="1.25" stroke-linecap="round"></path><path d="M5 5H18.3333" stroke="currentColor" stroke-width="1.25" stroke-linecap="round"></path><path d="M5.00016 18.3333C5.92064 18.3333 6.66683 17.5871 6.66683 16.6667C6.66683 15.7462 5.92064 15 5.00016 15C4.07969 15 3.3335 15.7462 3.3335 16.6667C3.3335 17.5871 4.07969 18.3333 5.00016 18.3333Z" stroke="currentColor" stroke-width="1.25"></path><path d="M14.1667 18.3333C15.0871 18.3333 15.8333 17.5871 15.8333 16.6667C15.8333 15.7462 15.0871 15 14.1667 15C13.2462 15 12.5 15.7462 12.5 16.6667C12.5 17.5871 13.2462 18.3333 14.1667 18.3333Z" stroke="currentColor" stroke-width="1.25"></path><path d="M6.6665 16.666H12.4998" stroke="currentColor" stroke-width="1.25" stroke-linecap="round"></path><path d="M1.6665 1.66602H2.4715C3.25874 1.66602 3.94495 2.18651 4.13589 2.92846L6.61527 12.5631C6.74056 13.05 6.63334 13.5658 6.32337 13.9673L5.52661 14.9993" stroke="currentColor" stroke-width="1.25" stroke-linecap="round"></path></svg>'
                    ],
                    'customers'     => [
                        'title'    => __('Customers', 'fluent-toolkit'),
                        'url'      => admin_url('admin.php?page=fluent-cart#/customers'),
                        'icon_svg' => '<svg viewBox="0 0 24 24" width="128" height="128" color="currentColor" fill="none"><path d="M5.5 21V20.25C5.5 16.7982 8.29822 14 11.75 14C15.2018 14 18 16.7982 18 20.25V21H19.5V20.25C19.5 17.0526 17.5628 14.3093 14.7988 13.125C16.4209 12.1084 17.5 10.3055 17.5 8.25C17.5 5.07436 14.9256 2.5 11.75 2.5C8.57436 2.5 6 5.07436 6 8.25C6 10.3052 7.07849 12.1083 8.7002 13.125C5.93662 14.3095 4 17.0529 4 20.25V21H5.5ZM11.75 12.5C9.40279 12.5 7.5 10.5972 7.5 8.25C7.5 5.90279 9.40279 4 11.75 4C14.0972 4 16 5.90279 16 8.25C16 10.5972 14.0972 12.5 11.75 12.5Z" fill="currentColor" /></svg>'
                    ],
                    'products'      => [
                        'title'    => __('Products', 'fluent-toolkit'),
                        'url'      => admin_url('admin.php?page=fluent-cart#/products'),
                        'icon_svg' => '<svg aria-hidden="true" width="16" height="16" viewBox="0 0 16 16"><path fill-rule="evenodd" clip-rule="evenodd" d="M7.973 0a2 2 0 0 0-.894.211L1.607 2.947A2 2 0 0 0 .5 4.737v6.568a2 2 0 0 0 1.052 1.761l4.782 2.575A3 3 0 0 0 7.757 16h.488a3 3 0 0 0 1.422-.359l4.781-2.575a2 2 0 0 0 1.052-1.76v-6.57a2 2 0 0 0-1.105-1.789L8.922.211A2 2 0 0 0 8.03 0h-.056ZM14 11.306V5.62L8.75 8.448v5.964c.07-.025.14-.056.206-.091l4.781-2.575a.5.5 0 0 0 .263-.44ZM8.252 1.553l5.257 2.629-2.06 1.109-5.38-2.898 1.68-.84a.5.5 0 0 1 .224-.053h.056a.5.5 0 0 1 .223.053ZM4.756 3.05 2.491 4.182 8 7.148l2.184-1.176L4.756 3.05ZM7.25 8.448 2 5.622v5.683a.5.5 0 0 0 .263.44l4.782 2.576c.066.035.134.066.204.09V8.449Z"></path></svg>'
                    ],
                    'subscriptions' => [
                        'title'    => __('Subscriptions', 'fluent-toolkit'),
                        'url'      => admin_url('admin.php?page=fluent-cart#/subscriptions'),
                        'icon_svg' => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M3 13.9998C3 13.6686 3.2688 13.3998 3.6 13.3998H15.2296C15.0976 13.2734 14.9544 13.1438 14.8064 13.0158C14.5168 12.7646 14.2256 12.5302 14.0056 12.359C13.896 12.2734 13.8048 12.2038 13.7416 12.1558C13.7096 12.1318 13.6848 12.1134 13.6688 12.1006L13.6496 12.087L13.644 12.083C13.3776 11.8862 13.32 11.511 13.5168 11.2438C13.7136 10.9774 14.0888 10.9206 14.356 11.1166L14.3584 11.1182L14.364 11.123L14.3856 11.139C14.404 11.1526 14.4312 11.1726 14.4648 11.1982C14.5328 11.2494 14.6288 11.323 14.744 11.4126C14.9744 11.5926 15.2832 11.8398 15.5936 12.1094C15.9008 12.3766 16.224 12.6766 16.4744 12.9606C16.5992 13.1022 16.7192 13.2534 16.8104 13.4054C16.8928 13.5406 17 13.7534 17 13.9998C17 14.2462 16.8928 14.459 16.8104 14.5942C16.7192 14.7462 16.5992 14.8974 16.4744 15.039C16.224 15.323 15.9008 15.623 15.5936 15.8902C15.2832 16.1598 14.9744 16.407 14.744 16.587C14.6288 16.6766 14.5328 16.7502 14.4648 16.8014C14.4312 16.8262 14.3768 16.867 14.3568 16.8822L14.356 16.883C14.0888 17.0798 13.7136 17.0222 13.5168 16.7558C13.32 16.4886 13.3776 16.1134 13.644 15.9166L13.6456 15.9158L13.6496 15.9126L13.6688 15.899C13.6848 15.8862 13.7096 15.8678 13.7416 15.8438C13.8048 15.7958 13.896 15.7262 14.0056 15.6406C14.2256 15.4694 14.5168 15.235 14.8064 14.9838C14.9544 14.8558 15.0984 14.7262 15.2296 14.5998H3.6C3.2688 14.5998 3 14.331 3 13.9998ZM17 5.99982C17 6.33102 16.7312 6.59982 16.4 6.59982H4.7704C4.9016 6.72623 5.0456 6.85582 5.1936 6.98382C5.4832 7.23502 5.7744 7.46943 5.9944 7.64063C6.104 7.72623 6.1952 7.79583 6.2584 7.84383C6.2904 7.86783 6.3152 7.88623 6.3312 7.89903L6.3504 7.91263L6.356 7.91663C6.6224 8.11343 6.6792 8.48863 6.4832 8.75583C6.2864 9.02223 5.9112 9.07903 5.644 8.88303L5.6416 8.88143L5.636 8.87663L5.6144 8.86063C5.596 8.84703 5.5688 8.82703 5.5352 8.80143C5.4672 8.75023 5.3712 8.67663 5.256 8.58703C5.0256 8.40703 4.7168 8.15983 4.4064 7.89023C4.0992 7.62303 3.776 7.32303 3.5256 7.03903C3.4008 6.89742 3.2808 6.74623 3.1896 6.59422C3.1072 6.45902 3 6.24622 3 5.99982C3 5.75342 3.1072 5.54062 3.1896 5.40542C3.2808 5.25342 3.4008 5.10222 3.5256 4.96062C3.776 4.67662 4.0992 4.37662 4.4064 4.10942C4.7168 3.83982 5.0256 3.59262 5.256 3.41262C5.3712 3.32302 5.4672 3.24942 5.5352 3.19822C5.568 3.17342 5.6232 3.13262 5.6432 3.11742L5.644 3.11662C5.9112 2.92062 6.2864 2.97742 6.4832 3.24382C6.68 3.51102 6.6224 3.88622 6.356 4.08302L6.3544 4.08382L6.3504 4.08702L6.3312 4.10062C6.3152 4.11342 6.2904 4.13182 6.2584 4.15582C6.1952 4.20382 6.104 4.27342 5.9944 4.35902C5.7744 4.53022 5.4832 4.76462 5.1936 5.01582C5.0456 5.14382 4.9016 5.27342 4.7704 5.39982H16.4C16.7312 5.39982 17 5.66862 17 5.99982Z" fill="currentColor"></path></svg>'
                    ],
                    'reports'       => [
                        'title'    => __('Reports', 'fluent-toolkit'),
                        'url'      => admin_url('admin.php?page=fluent-cart#/reports'),
                        'icon_svg' => '<svg viewBox="0 0 24 24" width="128" height="128" color="currentColor" fill="none"><defs /><path fill="currentColor" d="M4.25,3 L4.25,14 C4.25,15.671 4.252,16.849 4.371,17.74 C4.48,18.549 4.675,19.025 4.984,19.369 C5.16,19.016 5.351,18.582 5.571,18.085 L5.571,18.085 L5.704,17.784 C6.034,17.038 6.416,16.197 6.857,15.412 C7.296,14.631 7.817,13.864 8.445,13.285 C9.079,12.7 9.865,12.269 10.806,12.269 C12.091,12.269 12.897,13.093 13.463,13.672 L13.51,13.72 C14.15,14.374 14.533,14.722 15.114,14.722 C15.671,14.722 16.06,14.503 16.418,14.111 C16.797,13.696 17.101,13.133 17.469,12.452 L17.509,12.379 L17.515,12.367 C18.222,11.059 19.2,9.25 21.5,9.25 C21.914,9.25 22.25,9.586 22.25,10 C22.25,10.414 21.914,10.75 21.5,10.75 C20.194,10.75 19.6,11.666 18.828,13.092 L18.757,13.224 C18.42,13.849 18.034,14.566 17.526,15.123 C16.947,15.756 16.178,16.222 15.114,16.222 C13.856,16.222 13.059,15.406 12.501,14.834 L12.439,14.77 C11.806,14.124 11.41,13.769 10.806,13.769 C10.356,13.769 9.917,13.967 9.462,14.387 C9.001,14.813 8.571,15.425 8.165,16.147 C7.761,16.864 7.405,17.647 7.075,18.392 C7.032,18.489 6.989,18.587 6.946,18.684 C6.73,19.175 6.519,19.652 6.318,20.054 C6.453,20.082 6.6,20.107 6.759,20.128 C7.651,20.248 8.829,20.25 10.5,20.25 L21.5,20.25 C21.914,20.25 22.25,20.586 22.25,21 C22.25,21.414 21.914,21.75 21.5,21.75 L10.444,21.75 C8.842,21.75 7.563,21.75 6.56,21.615 C5.523,21.476 4.67,21.18 3.995,20.505 C3.32,19.83 3.024,18.977 2.885,17.94 C2.75,16.937 2.75,15.658 2.75,14.056 L2.75,3 C2.75,2.586 3.086,2.25 3.5,2.25 C3.914,2.25 4.25,2.586 4.25,3 Z M12.25,7 C12.25,7.414 11.914,7.75 11.5,7.75 L7.5,7.75 C7.086,7.75 6.75,7.414 6.75,7 C6.75,6.586 7.086,6.25 7.5,6.25 L11.5,6.25 C11.914,6.25 12.25,6.586 12.25,7 Z M8.5,4.75 L7.5,4.75 C7.086,4.75 6.75,4.414 6.75,4 C6.75,3.586 7.086,3.25 7.5,3.25 L8.5,3.25 C8.914,3.25 9.25,3.586 9.25,4 C9.25,4.414 8.914,4.75 8.5,4.75 Z" /></svg>'
                    ]
                ]
            ]
        ];

        $this->apps = $apps;


        add_action('admin_init', function () {
            global $plugin_page;
            if (!isset($this->apps[$plugin_page])) {
                return;
            }

            $hookName = 'toplevel_page_' . $plugin_page;

            add_action($hookName, [$this, 'pushUnifiedUiToTop'], 1);
            add_action($hookName, function () {
                echo '</div></div>';
            }, 9999);

            // disable top menu
            add_filter('show_admin_bar', '__return_false', 9999);

            //   add_action('in_admin_header', [$this, 'pushUnifiedUiToTop'], 9999);

            add_action('admin_enqueue_scripts', [$this, 'loadUnifiedUi']);
        });


    }

    public function loadUnifiedUi($screen = '')
    {
        $pagePluginName = (string)str_replace('toplevel_page_', '', (string)$screen);

        if (!isset($this->apps[$pagePluginName])) {
            return;
        }

        wp_enqueue_style('fluent_unified_ui', FLUENT_BETA_TESTING_PLUGIN_URL . 'src/unified-ui.css', [], FLUENT_TOOLKIT_VERSION);
    }

    public function pushUnifiedUiToTop()
    {
        global $plugin_page;
        $currentApp  = isset($this->apps[$plugin_page]) ? $this->apps[$plugin_page] : [];
        $currentItems = isset($currentApp['items']) ? $currentApp['items'] : [];
        $otherApps   = $this->apps;
        unset($otherApps[$plugin_page]);

        $siteName = get_bloginfo('name');
        $siteIcon = function_exists('get_site_icon_url') ? get_site_icon_url(64) : '';
        ?>

        <div class="fluent_uui">
        <button type="button" class="fui-mobile-toggle" aria-label="Toggle menu" aria-controls="fui-sidebar" aria-expanded="false">
            <span class="fui-mobile-toggle-bars" aria-hidden="true"></span>
        </button>
        <div class="fui-backdrop" hidden></div>
        <div id="fui-sidebar" class="fluent_ui_sidebar">

            <!-- Workspace switcher -->
            <div class="fui-workspace-wrap">
                <button type="button" class="fui-workspace" aria-haspopup="menu" aria-expanded="false">
                    <?php if ($siteIcon): ?>
                        <img class="fui-workspace-icon" src="<?php echo esc_url($siteIcon); ?>" alt="" />
                    <?php else: ?>
                        <span class="fui-workspace-icon fui-workspace-icon--initial"><?php echo esc_html(mb_strtoupper(mb_substr($siteName, 0, 1))); ?></span>
                    <?php endif; ?>
                    <div class="fui-workspace-info">
                        <div class="fui-workspace-name"><?php echo esc_html($siteName); ?></div>
                        <?php if (!empty($currentApp['title'])): ?>
                            <div class="fui-workspace-sub"><?php echo esc_html($currentApp['title']); ?></div>
                        <?php endif; ?>
                    </div>
                    <span class="fui-workspace-caret" aria-hidden="true"></span>
                </button>
                <div class="fui-workspace-menu" role="menu" hidden>
                    <a href="<?php echo esc_url(admin_url()); ?>" class="fui-workspace-menu-item" role="menuitem">
                        <span class="fui-workspace-menu-icon" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 4 6 8l4 4"/></svg>
                        </span>
                        Back to WP Admin
                    </a>
                    <a href="<?php echo esc_url(home_url('/')); ?>" target="_blank" rel="noopener" class="fui-workspace-menu-item" role="menuitem">
                        <span class="fui-workspace-menu-icon" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3H4a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-2"/><path d="M9 3h4v4"/><path d="m13 3-6 6"/></svg>
                        </span>
                        Visit Site
                    </a>
                    <div class="fui-workspace-menu-divider" role="none"></div>
                    <a href="<?php echo esc_url(wp_logout_url()); ?>" class="fui-workspace-menu-item fui-workspace-menu-item--danger" role="menuitem">
                        <span class="fui-workspace-menu-icon" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 12v1a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1v1"/><path d="M13 8H6"/><path d="m11 6 2 2-2 2"/></svg>
                        </span>
                        Log Out
                    </a>
                </div>
            </div>

            <!-- Current app's primary navigation -->
            <?php if (!empty($currentItems)): ?>
                <nav class="fui-main-nav" aria-label="<?php echo esc_attr($currentApp['title']); ?> navigation">
                    <ul>
                        <?php foreach ($currentItems as $itemKey => $item):
                            $itemHash = parse_url($item['url'], PHP_URL_FRAGMENT);
                            $hasSub   = !empty($item['sub_menu']);
                            ?>
                            <li class="fui-item<?php echo $hasSub ? ' fui-item--has-sub is-open' : ''; ?>">
                                <a href="<?php echo esc_url($item['url']); ?>"
                                   class="fui-apps-menu-item"
                                   data-fui-hash="<?php echo $itemHash ? '#' . esc_attr($itemHash) : ''; ?>">
                                    <?php if (!empty($item['icon_svg'])): ?>
                                        <span class="fui-app-icon"><?php echo $item['icon_svg']; ?></span>
                                    <?php endif; ?>
                                    <span class="fui-app-title"><?php echo esc_html($item['title']); ?></span>
                                    <?php if ($hasSub): ?>
                                        <span class="fui-item-chevron" aria-hidden="true"></span>
                                    <?php endif; ?>
                                </a>
                                <?php if ($hasSub): ?>
                                    <ul class="fui-submenu">
                                        <?php foreach ($item['sub_menu'] as $subKey => $subItem):
                                            $subHash = parse_url($subItem['url'], PHP_URL_FRAGMENT);
                                            ?>
                                            <li>
                                                <a href="<?php echo esc_url($subItem['url']); ?>"
                                                   class="fui-apps-submenu-item"
                                                   data-fui-hash="<?php echo $subHash ? '#' . esc_attr($subHash) : ''; ?>">
                                                    <span class="fui-app-title"><?php echo esc_html($subItem['title']); ?></span>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            <?php endif; ?>

            <!-- Other products -->
            <?php
            $visibleOtherApps = array_filter($otherApps, function ($a) {
                return empty($a['disabled']);
            });
            ?>
            <?php if (!empty($visibleOtherApps)): ?>
                <div class="fui-products">
                    <h4>Products</h4>
                    <ul>
                        <?php foreach ($visibleOtherApps as $slug => $app):
                            $appUrl   = admin_url('admin.php?page=' . $slug);
                            $appItems = isset($app['items']) ? $app['items'] : [];
                            ?>
                            <li class="fui-product">
                                <a href="<?php echo esc_url($appUrl); ?>" class="fui-product-head">
                                    <?php if (!empty($app['icon'])): ?>
                                        <span class="fui-app-icon fui-app-icon--img">
                                            <img src="<?php echo esc_url($app['icon']); ?>" alt="" />
                                        </span>
                                    <?php endif; ?>
                                    <span class="fui-app-title"><?php echo esc_html($app['title']); ?></span>
                                    <span class="fui-product-chevron" aria-hidden="true"></span>
                                </a>
                                <?php if (!empty($appItems)): ?>
                                    <ul class="fui-product-items">
                                        <?php foreach ($appItems as $appItemKey => $appItem): ?>
                                            <li>
                                                <a href="<?php echo esc_url($appItem['url']); ?>" class="fui-apps-submenu-item">
                                                    <span class="fui-app-title"><?php echo esc_html($appItem['title']); ?></span>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        <div class="fui-app-content">

        <script>
            (function () {
                var sidebar = document.querySelector('.fluent_ui_sidebar');
                if (!sidebar) return;

                var uuiRoot = document.querySelector('.fluent_uui');
                var mobileToggle = document.querySelector('.fui-mobile-toggle');
                var backdrop = document.querySelector('.fui-backdrop');

                function setMobileOpen(open) {
                    if (!uuiRoot) return;
                    uuiRoot.classList.toggle('is-mobile-open', open);
                    if (mobileToggle) mobileToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                    if (backdrop) backdrop.hidden = !open;
                    document.body.style.overflow = open ? 'hidden' : '';
                }

                if (mobileToggle && uuiRoot) {
                    mobileToggle.addEventListener('click', function () {
                        setMobileOpen(!uuiRoot.classList.contains('is-mobile-open'));
                    });
                }

                if (backdrop) {
                    backdrop.addEventListener('click', function () {
                        setMobileOpen(false);
                    });
                }

                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape' && uuiRoot && uuiRoot.classList.contains('is-mobile-open')) {
                        setMobileOpen(false);
                    }
                });

                function normalize(h) {
                    if (!h) return '';
                    return h.replace(/\/$/, '') || '#';
                }

                function applyActive() {
                    var current = normalize(window.location.hash || '#/');
                    var links = sidebar.querySelectorAll('[data-fui-hash]');
                    var bestEl = null;
                    var bestLen = -1;

                    links.forEach(function (el) {
                        el.classList.remove('active');
                        var target = normalize(el.getAttribute('data-fui-hash'));
                        if (!target) return;
                        if (current === target || (target !== '#' && current.indexOf(target) === 0)) {
                            if (target.length >= bestLen) {
                                bestLen = target.length;
                                bestEl = el;
                            }
                        }
                    });

                    if (bestEl) {
                        bestEl.classList.add('active');
                        var parentItem = bestEl.closest('.fui-item--has-sub');
                        if (parentItem) {
                            var parentLink = parentItem.querySelector(':scope > .fui-apps-menu-item');
                            if (parentLink && parentLink !== bestEl) {
                                parentLink.classList.add('is-parent-active');
                            }
                        }
                    }
                }

                applyActive();
                window.addEventListener('hashchange', applyActive);

                // Chevron click toggles inline expansion (no navigation).
                // Handles both product rows and items with sub-menus.
                // Clicking a top-level item that's closed also expands it (and still navigates).
                sidebar.addEventListener('click', function (e) {
                    var chevron = e.target.closest('.fui-product-chevron, .fui-item-chevron');
                    if (chevron) {
                        e.preventDefault();
                        e.stopPropagation();
                        var container = chevron.closest('.fui-product, .fui-item--has-sub');
                        if (container) container.classList.toggle('is-open');
                        return;
                    }

                    var itemLink = e.target.closest('.fui-apps-menu-item');
                    if (itemLink) {
                        var parent = itemLink.closest('.fui-item--has-sub');
                        if (parent && parent.querySelector(':scope > .fui-apps-menu-item') === itemLink) {
                            parent.classList.add('is-open');
                        }
                    }

                    // Mobile: any real navigation link click closes the drawer
                    if (uuiRoot && uuiRoot.classList.contains('is-mobile-open')) {
                        var navLink = e.target.closest('.fui-apps-menu-item, .fui-apps-submenu-item, .fui-product-head');
                        if (navLink) setMobileOpen(false);
                    }
                });

                // Workspace switcher dropdown.
                var wsWrap = sidebar.querySelector('.fui-workspace-wrap');
                if (wsWrap) {
                    var wsBtn = wsWrap.querySelector('.fui-workspace');
                    var wsMenu = wsWrap.querySelector('.fui-workspace-menu');

                    function closeWs() {
                        wsWrap.classList.remove('is-open');
                        wsBtn.setAttribute('aria-expanded', 'false');
                        wsMenu.hidden = true;
                    }
                    function openWs() {
                        wsWrap.classList.add('is-open');
                        wsBtn.setAttribute('aria-expanded', 'true');
                        wsMenu.hidden = false;
                    }

                    wsBtn.addEventListener('click', function (e) {
                        e.stopPropagation();
                        if (wsWrap.classList.contains('is-open')) closeWs();
                        else openWs();
                    });

                    document.addEventListener('click', function (e) {
                        if (!wsWrap.contains(e.target)) closeWs();
                    });

                    document.addEventListener('keydown', function (e) {
                        if (e.key === 'Escape' && wsWrap.classList.contains('is-open')) {
                            closeWs();
                            wsBtn.focus();
                        }
                    });
                }
            })();
        </script>
        <?php
    }

}
