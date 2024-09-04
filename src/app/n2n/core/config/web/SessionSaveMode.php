<?php

namespace n2n\core\config\web;

enum SessionSaveMode: string {
	case FILESYSTEM = 'filesystem';
	case APPCACHE = 'appcache';
}