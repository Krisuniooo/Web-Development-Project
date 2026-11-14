<?php

declare(strict_types=1);

// English chat messages with the rules each really breaks, for bin/measure.php. An empty list means a clean message.
// The rules are those of the questions in config/moderation.php:
// - racism: attacks a racial, ethnic or national group, or a person because of their origin
// - harassment: an insult, a threat or bullying aimed at a person; an attack on a whole group is racism only
// - profanity: a swear word or an obscene word; kill or die and a plain insult such as idiot are not one
// Most are clean, as on a real chat. "hard" marks a message that is easy to get wrong.
return [
    // racism
    ['text' => 'go back to your country you dirty immigrant', 'breaks' => ['racism', 'harassment']],
    ['text' => 'black people are all criminals', 'breaks' => ['racism']],
    ['text' => 'jews control the media, wake up', 'breaks' => ['racism']],
    ['text' => 'muslims are terrorists, every single one of them', 'breaks' => ['racism']],
    ['text' => 'mexicans are lazy and steal our jobs', 'breaks' => ['racism']],
    // hard: said as a joke
    ['text' => 'asians cant drive lol, typical', 'breaks' => ['racism']],
    ['text' => 'gypsies should be banned from this country', 'breaks' => ['racism']],

    // harassment
    ['text' => 'you are a worthless piece of trash, nobody likes you', 'breaks' => ['harassment']],
    ['text' => 'i will find you and kill you', 'breaks' => ['harassment']],
    ['text' => 'you are so ugly, no wonder you are alone', 'breaks' => ['harassment']],
    ['text' => 'kill yourself, nobody would miss you', 'breaks' => ['harassment']],
    ['text' => 'shut up you idiot', 'breaks' => ['harassment']],
    ['text' => 'you are the dumbest person on this server', 'breaks' => ['harassment']],
    ['text' => 'i know where you live, watch out', 'breaks' => ['harassment']],

    // profanity
    ['text' => 'shut the fuck up you idiot', 'breaks' => ['harassment', 'profanity']],
    ['text' => 'what a fucking great match', 'breaks' => ['profanity']],
    ['text' => 'shit, i forgot my password', 'breaks' => ['profanity']],
    ['text' => 'this patch is bullshit', 'breaks' => ['profanity']],
    // hard: a hidden letter
    ['text' => 'f*ck, lag again', 'breaks' => ['profanity']],
    ['text' => 'holy shit that was close', 'breaks' => ['profanity']],
    ['text' => 'fuck off', 'breaks' => ['harassment', 'profanity']],
    ['text' => 'you dumb bitch', 'breaks' => ['harassment', 'profanity']],

    // clean
    // hard: about a game
    ['text' => 'this boss killed me five times lol', 'breaks' => []],
    ['text' => 'gg well played everyone', 'breaks' => []],
    ['text' => 'i am so bad at this game today', 'breaks' => []],
    ['text' => 'my friend from Poland makes great food', 'breaks' => []],
    // hard: colours of game items
    ['text' => 'the black skin looks better than the white one', 'breaks' => []],
    ['text' => 'the character has dark skin and white hair', 'breaks' => []],
    ['text' => 'who is playing tonight?', 'breaks' => []],
    // hard: criticism of the play or the idea, not of the person
    ['text' => 'you played badly, learn the game', 'breaks' => []],
    ['text' => 'that was a stupid idea, read it again', 'breaks' => []],
    // hard: threats inside a game
    ['text' => 'im gonna kill you in the next round haha', 'breaks' => []],
    ['text' => 'i will destroy you all tonight in ranked', 'breaks' => []],
    // hard: slang, not a nation
    ['text' => 'that guy is a total chad, carried the whole team', 'breaks' => []],
    ['text' => 'sigma grindset, up at 5am again', 'breaks' => []],
    ['text' => 'black holes are terrifying', 'breaks' => []],
    ['text' => 'white moves first in chess', 'breaks' => []],
    ['text' => 'i visited nigeria last summer, amazing people', 'breaks' => []],
    // hard: talking about racism, or quoting it to condemn it
    ['text' => 'racism is stupid and we should fight it', 'breaks' => []],
    ['text' => 'someone said "go back to your country" earlier and that was disgusting', 'breaks' => []],
    ['text' => 'the history of slavery is a hard topic for my essay', 'breaks' => []],
    ['text' => 'the movie about the holocaust was moving', 'breaks' => []],
    ['text' => 'my grandma was Roma and played the violin', 'breaks' => []],
    // hard: a mild word in place of a swear word
    ['text' => 'dang it, i forgot my headset', 'breaks' => []],
    ['text' => 'this update sucks', 'breaks' => []],
    ['text' => 'this game is trash', 'breaks' => []],
    // hard: friendly
    ['text' => 'you are insane, that play was amazing', 'breaks' => []],
    ['text' => 'i am dying of laughter', 'breaks' => []],
    ['text' => 'can someone help me with this quest?', 'breaks' => []],
    ['text' => 'thanks for the help mate', 'breaks' => []],
    ['text' => 'when is the next event?', 'breaks' => []],
    ['text' => 'anyone from Vietnam here? looking for a team', 'breaks' => []],
    ['text' => 'brb getting coffee', 'breaks' => []],
    ['text' => 'sorry, my mistake', 'breaks' => []],
    ['text' => 'admin can you pin the rules?', 'breaks' => []],
    ['text' => 'poland lost again, what a disaster', 'breaks' => []],
    ['text' => 'chinese food tonight?', 'breaks' => []],
    ['text' => 'this bot is fast', 'breaks' => []],
];
