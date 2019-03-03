<?php

namespace Alexa\Response;

class Card {
	public $type = 'Standard';
	public $title = '';
	public $content = '';
	public $image = Array("smallImageUrl" => SERVER_HOME."/images/menus/home_108.png",
					"largeImageUrl" => SERVER_HOME."/images/menus/home_512.png");

	public function render() {
		return [
			'type' => $this->type,
			'title' => $this->title,
			'content' => $this->content
		];
	}
}
