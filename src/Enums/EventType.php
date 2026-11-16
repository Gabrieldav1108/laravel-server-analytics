<?php

declare(strict_types=1);

namespace Gabrieldav1108\ServerAnalytics\Enums;

enum EventType: string
{
    case Login = 'session.login';
    case Logout = 'session.logout';
    case LoginFailed = 'session.login_failed';
    case PageView = 'page.view';
    case Action = 'action';
}
