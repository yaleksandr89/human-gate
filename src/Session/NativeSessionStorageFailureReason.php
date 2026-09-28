<?php

declare(strict_types=1);

namespace Yaleksandr\HumanGate\Session;

enum NativeSessionStorageFailureReason: string
{
    case UnsupportedSession = 'unsupported_session';
    case SessionIdentity = 'session_identity';
    case HeadersSent = 'headers_sent';
    case MalformedState = 'malformed_state';
    case SizeLimit = 'size_limit';
    case Randomness = 'randomness';
    case Commit = 'commit';
    case Verification = 'verification';
}
