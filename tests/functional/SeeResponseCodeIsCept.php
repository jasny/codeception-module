<?php
$I = new FunctionalTester($scenario);
$I->wantTo('see a 404 response code');

$I->amOnPage('/not-found');
$I->seeResponseCodeIs(404);
