<?php

namespace RTC\Watcher;

enum Event: int
{
    case ON_ACCESS = 1;
    case ON_MODIFY = 2;
    case ON_ATTRIB = 4;
    case ON_CLOSE_WRITE = 8;
    case ON_CLOSE_NOWRITE = 16;
    case ON_OPEN = 32;
    case ON_MOVED_FROM = 64;
    case ON_MOVED_TO = 128;
    case ON_CREATE = 256;
    case ON_DELETE = 512;
    case ON_DELETE_SELF = 1024;
    case ON_MOVE_SELF = 2048;
    case ON_UNMOUNT = 8192;
    case ON_Q_OVERFLOW = 16384;
    case ON_IGNORED = 32768;
    case ON_CLOSE = 24;
    case ON_MOVE = 192;
    case ON_ALL_EVENTS = 4095;
    case ON_ONLYDIR = 16777216;
    case ON_DONT_FOLLOW = 33554432;
    case ON_MASK_ADD = 536870912;
    case ON_ISDIR = 1073741824;
    case ON_ONESHOT = 2147483648;

    case ON_CLOSE_NOWRITE_HIGH = 1073741840;
    case ON_OPEN_HIGH = 1073741856;
    case ON_CREATE_HIGH = 1073742080;
    case ON_DELETE_HIGH = 1073742336;

    case UNKNOWN = 0;
}