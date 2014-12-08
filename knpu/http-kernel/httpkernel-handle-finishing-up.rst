HttpKernel::handle() Finishing with kernel.response and kernel.exception
========================================================================

Because we're returning a Response, we skip the entire ``kernel.view`` block
and we skip straight down to the ``filterResponse()`` call. Notice that this
is also called up at the top. If one of our ``kernel.request`` listeners
sets the response, it *also* goes through this function. So no matter *how*
we create the response, it goes through the ``filterResponse()`` function.

What does it do? You can probably guess: it dispatches another event, and
this time, it's called ``kernel.response``. What's awesome about ``kernel.response``?
Listeners to it have access to the Response object. So if you need to modify
the response in some way, like adding a header, you can do that.

In fact, the web debug toolbar works via a listener to this event. When we
load up a page, we see the web debug toolbar at the bottom. The way that
works is that at the bottom of the HTML source, there's a bunch of JavaScript,
and this JavaScript makes an AJAX request that loads that bar. So how does
this JavaScript get there? The answer: there's a listener on the ``kernel.response``
event that injects that extra JavaScript into the HTML code of our page when
we're in development mode.

So all ``filterResponse()`` does is give us another hook point. It also calls
``finishRequest()``, which is honestly less important. It dispatches another
event and removes our request from the request stack. This is something we
haven't talked about too much yet, but the request stack is an object that
keeps track of all the requests we're processing. We'll talk about the idea
of subrequests in just a second.

Ultimately, the Response object is returned *all* the way back to our ``app_dev.php``
file. What do we do with it? We call ``send()``. This sends all the headers
by calling PHP's ``header()`` function and echo's the content.

The last thing that happens is ``$kernel->terminate()``, which is back inside
HttpKernel. If you look at this, it just dispatches yet *another* event.
Listeners to this event are able to do work *after* the response has already
been sent to the user. So if you have something heavy, like sending an email,
you could queue the email to be sent inside of your controller, but offload
the sending to a listener on this event. The user would see the page first,
and *then* the email would be sent. Now, you have to have your web server
setup correctly, and right now you need to be using PHP FPM for this to work,
but we have that all documented.

The only event that we *didn't* talk about that's important is another that's
in the "Not Called Listeners" list: ``kernel.exception``. Look back at the
original ``handle()`` function that had the try-catch block. You can probably
guess what's about to happen. If there *was* an exception, this calls ``handleException()``.
This lives right inside HttpKernel and - surpise! It dispatches an event,
this one is called ``kernel.exception``. The purpose of a listener to this
event is to look at the exception object that was thrown, and somehow convert
that to a Response. Because even if the servers are on fire, our users ultimately
need a Response: they need to see an illustration showing that our servers
are on fire. So, *some* listener needs to be responsible for creating that
final response.

And that's exactly what the code does. After dispatching it, it checks to
see if the event *has* a response. And if it doesn't, it finally has to give
up and just throw that exception. 

When we *do* get an exception - for example the 404 exception - this nice,
pretty exception page while we're developing - and also the error templates
that are shown in production - is created by a listener to this event called
``ExceptionListener``. I know, not the most creative name ever. Anyways,
if you want to see how the exception handling works inside Symfony, you can
open up this ``ExceptionListener`` and trace through some of its code. I
won't talk about this right now, but it uses a sub-request! And that's the
next topic.

And to be *really* cool, you can register your *own* event listener and do
whatever the heck you want with it, like showing an XKCD comic to random
users to your 404 page. Ya know, get creative.

Call me crazy, but when we zoom out, I think the request-response flow for
Symfony is pretty darn simple: we dispatch an event called ``kernel.request``
and it runs the routing. Them the resolver then figures out which controller
to call and we call it. At the end, we dispatch another event called ``kernel.response``
and that's basically it.

And if you're ever unsure about this stuff, go back and look at the Timeline,
because now, it tells a beautiful story. At the top, we see that ``kernel.request``
happens first, and everything below its bar are listeners to this it. Then,
it figures out which controller we want - that's the ``controller.get_callable``
piece. Cool. Next, it dispatches ``kernel.controller``, and you can see *its*
listeners. After that, the ``controller`` bar is our controller being called,
and the stuff under that is *our* work. We can see some Doctrine calls we're
making and the time it takes to render the template. After the controller,
the ``kernel.response`` event is dispatched, it has a few listeners, and
there's ``kernel.terminate``. Phew! 

So guys, it's just events, call the controller, then more events. Yep, that's
it. And now that we understand the deep dark core of the request-response
flow, let's bend this to our will to do some crazy, custom things.
