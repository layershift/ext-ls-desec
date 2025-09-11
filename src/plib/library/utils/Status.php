<?php

namespace PleskExt\Utils;

enum Status: string
{
    case STATUS_ERROR = "Error";
    case STATUS_REGISTERED = "Registered";
    case STATUS_NOT_REGISTERED = "Not Registered";
}