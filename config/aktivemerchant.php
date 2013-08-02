<?php

return array(
    'default_gateway' => 'Elavon',
    'log_level' => Log::DEBUG,
    'Elavon' => array(
        'login' => '000076', // merchant id
        'user' => '000076',
        'password' => 'R5DI0H', // this is actually the pin?
        'test' => TRUE
    ),
    'Beanstream' => array(
        'login' => '224100000', // merchant id
        'hash_key' => '3mpaw!um', // for md5 hashing
        'secure_profile_api_key' => '07AF75707D9147E2B334E47ABC470AAB' // payment profiles api passcode
    ),
    'Bluepay'=>array(
        'account_id'=>'',
        'test_mode'=>true,
        'secret_key'=>''
    )
);
