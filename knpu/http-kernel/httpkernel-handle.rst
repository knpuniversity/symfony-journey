HttpKernel::handle() The Heart of Everything
============================================

We know we start with the request, we have a routing layer, eventually something
calls the controller, and the controller returns a Response. Let's trace
through the process in Symfony to see how all of this works. It's going to
be awesome, I promise.

Front Controller: app.php/app_dev.php
-------------------------------------

Since we're using the built-in web server, this is actually executing the
``app_dev.php`` file in the ``web/`` directory. So let's go there to start.
And really, ``app.php`` is basically identical, so it doesn't matter which
one we start going through. Ignore all the stuff at the top of ``app_dev.php``,
I want to get to the good stuff::

    // web/app_dev.php
    use Symfony\Component\HttpFoundation\Request;
    // ...

    $kernel = new AppKernel('dev', true);
    $kernel->loadClassCache();
    $request = Request::createFromGlobals();
    $response = $kernel->handle($request);
    $response->send();
    $kernel->terminate($request, $response);

The first important thing is that we instantiate the ``AppKernel``, which
is the same ``AppKernel`` you have in your ``app/`` directory where you register
your bundles. We'll talk more about this class in another part of this series.
We'll also talk then about this ``loadClassCache``, but for now, comment
it out - it can get in the way if you're debugging core classes::

    // web/app_dev.php
    use Symfony\Component\HttpFoundation\Request;
    // ...

    $kernel = new AppKernel('dev', true);
    // comment this out for now
    // $kernel->loadClassCache();

    $request = Request::createFromGlobals();
    $response = $kernel->handle($request);
    $response->send();
    $kernel->terminate($request, $response);

For now, just know that ``AppKernel`` is the heart of your app. The first
*real* important thing I want to look at is the ``Request::createFromGlobals``.
We all know that we start with the request, and hey, here's where that Request
is born! The ``Request`` is just a simple object that holds data, and this
creates a new one that's populated from the `superglobal variables`_ - those
things like ``$_GET``, ``$_POST``, ``$_SERVER``. In fact, if we look inside,
it's creating an instance of itself populated from those variables::

    // vendor/symfony/symfony/src/Symfony/Component/HttpFoundation/Request.php
    // ...

    public static function createFromGlobals()
    {
        $request = self::createRequestFromFactory($_GET, $_POST, array(), $_COOKIE, $_FILES, $_SERVER);
        // ...

        return $request;
    }

The Most Important Function in Symfony
--------------------------------------

Now that we're starting with the request object, the next line is the most
important line inside of Symfony: the ``$kernel->handle()`` method::

    // web/app_dev.php
    // ...

    $response = $kernel->handle($request);
    // ...

What's really cool about this is that you see that the ``handle()`` method
has input request, output response. And that is actually *amazing*. It means
that everything that our application is - the routing, the controller, the
services - all of that stuff happens inside of this ``handle()`` function.
It also means that our entire application - all of those layers - have been
boiled down to a single function. And how pretty is it: input request, output
response. Because after all, that's our job: read the incoming request, create
a response.

Why Yes, you can Handle Many Requests at Once
---------------------------------------------

What I want to do with you guys is look inside that ``handle()`` function
and see what's going on. But first, I kind of want to prove a point about
how our application has just been reduced down to a single function. One
of the things about a well-written function is that you can call it over
and over again. So in theory, if we wanted to here, we could actually create
a second request by hand. I'll use a ``create()`` function::

    // web/app_dev.php
    // ...

    $request2 = Request::create('/dinosaurs/22');

What I want to do is process *two* requests at once. As you'll see later,
internally in Symfony, there actually is a real use-case for this. But for
now, I'm just playing around, so we'll have this request look like it wants
to go to ``/dinosaurs/22``, which is.... one of the pages we actually have
right now.

Then we'll say ``$response2 = $kernel->handle($request2);``. We're processing
two separate requests and processing two separate responses. I'm going to
comment out the ``send()`` and ``terminate()`` calls temporarily, and to
prove that things are working, I'm going to echo the content from the original
request, then echo the content from the second response. So hey, let's see
if we can print out two pages at once::

    // web/app_dev.php
    // ...

    $request = Request::createFromGlobals();
    $response = $kernel->handle($request);

    $request2 = Request::create('/dinosaurs/22');
    $response2 = $kernel->handle($request2);

    echo $response;
    echo $response2;

When we go back and refresh, this is really cool! On top, we see page that
we're actually going to, and below, we see the whole other page that was
processed beneath that. Our application is just a function: input request,
output response. And that's a really powerful thing to realize.

Let me undo all of this, and get back to where we started.

Introducing HttpKernel::handle()
--------------------------------

Let's look inside of that ``$kernel->handle()`` method. Again, the ``$kernel``
class is our ``AppKernel``. If I hold cmd (or ctrl for other OS's) and click
into the ``handle()`` function, it's going to take us not into ``AppKernel``,
but its parent class ``Kernel``. This class is something we're going to talk
about in a different part of this series. Ignore it for now, because it offloads
the work to something called ``HttpKernel``.

I'll use a shortcut my editor to open ``HttpKernel``. In PhpStorm, you can
go to Navigate, then Class or File. So I'll use the Cmd+O shortcut to open
up the ``HttpKernel`` class. We're looking for that ``handle()`` function.
Because effectively, when we call ``handle()`` in ``app_dev.php``, it's being
passed to this ``handle()`` function - you can see the ``$request`` argument::

    // vendor/symfony/symfony/src/Symfony/Component/HttpKernel/HttpKernel.php
    // ...

    public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        try {
            return $this->handleRaw($request, $type);
        } catch (\Exception $e) {
            if (false === $catch) {
                $this->finishRequest($request, $type);

                throw $e;
            }

            return $this->handleException($e, $request, $type);
        }
    }

Awesome! Now, what is handle actually doing? So far, not much. The important
thing to take-away here is that there is a try-catch block. This means that
if you throw an exception from anywhere inside your application - like a
controller or a service - it's going to get caught by this block. And when
that happens, you'll get passed to that ``handleException()`` function, which
is what tries to figure out what response to send back to the user when there's
an error. That's something we'll talk about later.

The real guts of this are in a function called ``handleRaw()``. And this
lives just a little bit further down inside this same class::

    private function handleRaw(Request $request, $type = self::MASTER_REQUEST)
    {
        // about 45 lines of awesome that we'll walk-through
    }

We're going to walk through every line in this function. You can see that
it's not that long, and what's really amazing is that ``handleRaw`` *is*
the Symfony Framework. This is the dark core guts of it. But it's also the
dark, core guts of Drupal 8, and the dark, core guts of Silex, of PhpBB.
All these very different pieces of software all use this same function. How
is that possible? How could this one function be responsible for a Symfony
application and a Drupal 8 application and a PhpBB application? We'll find
out.

.. _`superglobal variables`: http://php.net/manual/en/language.variables.superglobals.php
