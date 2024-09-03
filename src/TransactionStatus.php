<?php

namespace App;

enum TransactionStatus: string
{
    case Created = 'default';
    case Process = 'warning';
    case Success = 'success';
    case Error = 'danger';
}