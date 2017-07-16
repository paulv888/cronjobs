<?php

namespace Alexa\Response;

class Card {
	public $type = 'Standard';
	public $title = '';
	public $content = '';
	public $image = Array("smallImageUrl" => "https://vlohome.no-ip.org/images/menus/home_108.png",
					"largeImageUrl" => "https://vlohome.no-ip.org/images/menus/home_512.png");

	
	public function render() {
		return [
			'type' => $this->type,
			'title' => $this->title,
			'content' => $this->content
		];
	}
}