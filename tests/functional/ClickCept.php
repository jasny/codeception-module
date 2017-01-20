<?php
$I = new FunctionalTester($scenario);
$I->wantTo('click a link and see a change in url');

$I->amOnPage('/');
$I->click('Ping Test');

$I->seeCurrentUrlEquals('/api/ping');
$I->seeHttpHeader('Content-Type', 'application/json');
$I->see("ack");
$I->dontSee('HTTP messages are the foundation of web development');