<?php

return [

    'category_name_taken' => '已經有同名的分類。',
    'exercise_name_taken' => '已經有同名的項目。',
    'category_type_mismatch' => '只能移到同為「:type」的分類。',
    'category_not_empty' => [
        'title' => '還不能刪除「:name」',
        'message' => '這個分類還有 :count 個項目。先刪除項目，或把項目移到其他「:type」分類，再刪除分類。',
    ],
    'restore_name_taken' => [
        'title' => '還不能還原「:name」',
        'message' => '已經有同名的項目。先把那個項目改名或封存，再還原。',
    ],
    'restore_no_category' => [
        'title' => '還不能還原「:name」',
        'message' => '原本的分類已刪除，也沒有其他「:type」分類可以放。先新增一個「:type」分類，再還原。',
    ],

];
