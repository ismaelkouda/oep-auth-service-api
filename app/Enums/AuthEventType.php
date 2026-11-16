<?php

namespace App\Enums;

enum AuthEventType: string
{
    case LoginSucceeded = 'login_succeeded';
    case LoginFailed = 'login_failed';
    case OtpRequested = 'otp_requested';
    case OtpVerified = 'otp_verified';
    case OtpFailed = 'otp_failed';
    case Registered = 'registered';
    case Logout = 'logout';
    case PasswordChanged = 'password_changed';
    case PasswordChangeFailed = 'password_change_failed';
    case PasswordDefined = 'password_defined';
    case InvitationFailed = 'invitation_failed';
    case IntrospectionDenied = 'introspection_denied';
    case StaffCreated = 'staff_created';
    case StaffUpdated = 'staff_updated';
    case StaffSuspended = 'staff_suspended';
    case StaffReactivated = 'staff_reactivated';
    case StaffUnlocked = 'staff_unlocked';
    case StaffInvited = 'staff_invited';
    case StaffDeleted = 'staff_deleted';
}
