# Creating a Container in the Wild

A whole mini-series on Symfony's Dependency Injection Container? Yes! Do
you want to *really* understand how Symfony works - and also Drupal 8?
Then you're in the right place.

That's because Symfony is 2 parts. The first is the request/routing/controller/response
and event listener flow we talked about in the first Symfony Journey part.
The second half is all about the container. Understand it, and you'll unlock
everything.

## Setting up the Playground

Symfony normally gives you the built container. Instead of that, let's do
a DIY project and create that by hand. Actually, let's get out of Symfony
entirely. Inside the directory of our project, create a new folder called
`dino_container`. We're going to create a PHP file in here where we can mess
around - how about `roar.php`.

This file is *all* alone - it has *nothing* to do with the framework or our
project at all. We're flying solo.

I'll add a namespace `Dino\Play` - but only because it makes PhpStorm 
auto-complete my `use` statements nicely.

Let's require the project's autoloader - so go up one directory, then get
`vendor/autoload.php`:

[[[ code('b7212b97f9') ]]]

Great, now we can access Symfony's DependencyInjection classes and a few other
libraries we'll use, like Monolog.

## Using Monolog Straight-Up

In fact, forget about containers and Symfony and all that weird stuff. Let's
*just* use the Monolog library to log some stuff. That's simple, just
`$logger = new Logger()`. The first argument is the channel - it's like a
category - you'll see that word `main` in the logs. Now log something:
`$logger->info()`, then ROOOAR:

[[[ code('bc66394fa9') ]]]

Ok, let's see if we can get this script to yell back at us. Run it with:

```bash
php dino_container/roar.php
```

Fantastic! If you don't do anything, Monolog spits your messages out into
stderr.

To pretend like this little file is an application, I'll create a `runApp()`
function that does the yelling. Pass it a `$logger` argument and move our
`info()` call inside:

[[[ code('44dda80c2e') ]]]

I'm just doing this to separate my setup code - the part where I configure
objects like the Logger - from my *real* application, which in this case,
roars at us. It still works like before.

## Create a Container

Now, to the container? First, the basics:

1. A service is just a fancy name a computer science major made up to describe
   a useful object. A logger is a useful object, so it's a service. A mailer
   object, a database connection object and an object that talks to your
   coffee maker's API: all useful objects, all services.

2. A container is an object, but it's really just an associative array that
   holds all your service objects. You ask it for a service by some nickname,
   and it gives you back that object. And it has some other super-powers
   that we'll see later.

Got it? Great, create a `$container` variable and set it to a new `ContainerBuilder`
object.

[[[ code('2eb4e84ed3') ]]]

Hello Mr Container! Later, we'll see why Mr Container is called a builder.

Working with it is simple: use `set` to put a service into it, and `get` to
fetch that back later. Call `set` and pass it the string `logger`. That's
the key for the service - it's like a nickname, and we could use anything we
want.

**TIP** The standard is to use lowercase characters, numbers, underscores
and periods. Some other characters are illegal and while service ids are case *insensitive*,
using lower-cased characters is faster. Want details? See
[github.com/knpuniversity/symfony-journey/issues/5](https://github.com/knpuniversity/symfony-journey/issues/5).

Then pass the `$logger` object:

[[[ code('8bff370c01') ]]]

Now, pass `$container` to `runApp` instead of the logger and update its
argument. To fetch the logger from the container, I'll say `$container->get()`
then the key - `logger`:

[[[ code('89644f4b79') ]]]

The logger service goes into the container with `set`, and it comes back
out with `get`. No magic.

Test it out:

```bash
php dino_container/roar.php
```

Yep, still roaring.

## Adding a Second Service

A real project will have *a lot* of services - maybe hundreds. Let's add a
second one. When you log something, monolog passes that to handlers, and
they actually do the work, like adding it to a log file or a database.

Create a new `StreamHandler` object - we can use it to save things to a file.
We'll stream logs into a `dino.log` file:

[[[ code('d855267c80') ]]]

Next, pass an array as the second argument to our Logger with this inside:

[[[ code('a3996686ec') ]]]

Cool, so try it out. Oh, no more message! It's OK. As soon as you pass at
least *one* handler, Monolog uses that instead of dumping things out to the
terminal. But we *do* now have a `dino.log`.

With things working, let's also put the stream handler into the container.
So, `$container->set()` - and here we can make up any name, so how about
`logger.stream_handler`. Then pass it the `$streamHandler` variable:

[[[ code('52498e181d') ]]]

Down in the `$logger`, just fetch it out with `$container->get('logger.stream_handler')`:

[[[ code('3341b50557') ]]]

PhpStorm is highlighting that line - don't let it boss you around. It gets
a little confused when I create a Container from scratch inside a Symfony
project.

Try it out:

```bash
php dino_container/roar.php
tail dino_container/dino.log
```

Good, no errors, and when we tail the log, 2 messages - awesome!

Up to now, the container isn't much more than a simple associative array.
We put 2 things in, we get 2 things out. But we're not really exercising
the true power of the container, yet.
