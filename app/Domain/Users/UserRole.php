<?php

namespace App\Domain\Users;

enum UserRole: string
{
    case Admin = 'admin';
    case Owner = 'pemilik';
}
