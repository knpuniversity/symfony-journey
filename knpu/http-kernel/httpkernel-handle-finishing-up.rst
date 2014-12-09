Finishing with kernel.response and kernel.exception
===================================================

Our controller returns a Response, so we skip the entire ``kernel.view`` block
and go straight down to the ``filterResponse()`` call. This function shows
up in one other place further up. If one of our ``kernel.request`` listeners
sets the response, the response *also* goes through this function. So no
matter *how* we create the response, ``filterResponse()`` is called.

What does it do? Come on, you should be able to guess by now. It dispatches
yet another event, and this time, it's called ``kernel.response``. What's
awesome about ``kernel.response``? Listeners to it have access to the Response
object by calling ``getResponse()`` on the ``FilterResponseEvent`` object.
So if you want to modify the response, like adding a header, this is your
event.

In fact, the web debug toolbar works via a listener to this event. When we
load up a page, the web debug toolbar shows up at the bottom. This works
because at the bottom of the HTML source, there's a bunch of JavaScript that
makes an AJAX request that loads it. But how does this JavaScript get there?
The answer: with a listener on the ``kernel.response`` event. That listener
injects the extra JavaScript into the HTML code of our page when we're in
development mode.

So all ``filterResponse()`` does is give us another hook point. It also calls
``finishRequest()``, which is honestly less important. It dispatches another
event and removes our request from the request stack. The request stack is
an object that keeps track of all the requests we're processing. We'll talk
about subrequests in a second, and then it'll make sense how Symfony can
be handling multiple requests at once.

After all this, the Response object is returned *all* the way back to ``app_dev.php``.
We made it through Symfony's core and came back out alive with the response
in hand.

What do we do with it? We call ``send()``. This sends all the headers with
the ``header()`` function and echo's the content.

kernel.terminate
----------------

The absolute last thing that happens is ``$kernel->terminate()``, which is
back inside HttpKernel. Surprise! It dispatches *another* event called ``kernel.terminate``.
Listeners to this event are able to do work *after* the response has already
been sent to the user. In other words, you can do work *after* your user
is already happily seeing the page. Crazy, right?

So if you have something heavy, like sending an email, you could queue the
email to be sent inside of your controller, but offload the sending to a
listener on this event. The user would see the page first, and *then* the
email would be sent. You have to have your web server setup correctly, but
we have that all documented.

kernel.exception
----------------

That's it guys - there's nothing more to see... unless something goes wrong.
In the "Not Called Listeners" list, there's one more important event: ``kernel.exception``.
Look back at the original ``handle()`` function that had the try-catch block.
You can probably guess what's about to happen. If there *was* an exception,
this calls ``handleException()``. This lives further below and - surpise!
It dispatches an event called ``kernel.exception``.

The purpose of a listener to this event is to look at the exception object
that was thrown, and somehow convert that to a Response. Because even if the
servers are on fire, our user ultimately need a Response: they need to see
an illustration showing that our servers are being eaten by gremlins.
*Some* listener needs to create that final response for us.

And that's exactly what this code does. After dispatching the event, it checks
to see if the event *has* a response and gives up if no listeners have helped
out.

In the real world, when an exception is thrown - like on a 404 page - we see
this pretty exception page while we're developing. In the ``prod`` environment,
we would see an error template. These responses are created by a listener
to this event called ``ExceptionListener``. I know, not the most creative
name. Anyways, if you want to see how the exception handling works inside
Symfony, you can open up this ``ExceptionListener`` and trace through some
of its code. I won't talk about this right now, but it uses a sub-request!
And that's the next topic.

To be *really* hip, you could register your *own* event listener and do
whatever the heck you want with it, like showing an XKCD comic to random
users on your 404 page. Ya know, get creative.

Call me crazy, but when we zoom out, I think the request-response flow for
Symfony is pretty darn simple. It's basically: event, controller, event.

Go back and look at the Timeline in the profiler, because now, it tells a
beautiful story. At the top, we see that ``kernel.request`` happens first,
and everything below its bar are listeners. Then, it figures out which controller
we want - that's the ``controller.get_callable`` part. Cool. Next, it dispatches
``kernel.controller``, and you can see *its* listeners. After that, the
``controller`` is called and the stuff under that is *our* work. We can see
some Doctrine calls we're making and the time it takes to render the template.
After the controller, the ``kernel.response`` event is dispatched, it has
a few listeners, and there's ``kernel.terminate``. Brilliant!

So guys, it's just events, call the controller, then more events. Yep, that's
it. And now that we've journeyed to the core of Symfony's request and response
flow, let's bend this to our will to do some crazy, custom things.
