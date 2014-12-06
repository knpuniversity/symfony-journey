The kernel.view Event
=====================

We're now *really* close to being done with the request-response flow. We've
seen the kernel.request event, which ran the router. We saw the ControllerResolver
instantiate our controller object, and we also saw how this getArguments
method iterates over the arguments in our controller and matches them up
with the what came back from the router.

Our controller is returning a Response object. We're calling the ``render()``
function and that's a shortcut to render the template and put it inside of
a Response object. But in reality, you don't *have* to return a Response
from your controller: you can return whatever you want. If you don't return
a response, what does Symfony do? It dispatches another event. This time
it's called ``kernel.view``. And the purpose of listeners to this event is
to save Symfony. Symfony *needs* a Response object, and if the controller
didn't give it to us, it's going to call all of the listeners to this event
and say: "Hey, here's what the controller *did* return, can you somehow
transform this into a real response."

Why would you do this? In a true MVC framework, the controller is supposed
to not return a response, but instead just return some data. Imagine if
our DinosaurController::showAction didn't return a Response, it just returned
a Dinosaur object. And *then*, what if we had a listener on this event that
was capable of seeing that Dinosaur object, and rendering the template and
creating the Response for us. In that case, we're splitting the fetching
and preparation of the data and the creation of a representation of that
data into two pieces. Why would you do this? One potential use-case is if
you have a REST API where every URI needs to be able to return both an HTML
response and a JSON response based on an ``Accept`` header the client sends.
In your controller, instead of having an ``if`` statement that tries to see
which version we need, your controller would just return the data. Then you'd
have a listener on this event that's smart enough to see that the user wants
HTML or the user wants JSON. If the user wants HTML, it would render the
template. If it wants JSON, it would take that Dinosaur object and turn it
into JSON. The FOSRestBundle has something that does exactly this: you can
just return some data from your controller, and it has a listener on the
``kernel.view`` event that transforms that data into whatever response is
appropriate.

In normal Symfony, there's nothing important that listens to this. But when
it's called, it's hoping that one of those listeners will be able to set the
response on the ``$event`` object. And ultimately, if we *still* don't have
a Response, *that's* when you get the error that the controller must return
a Response. And one of my favorite parts of the code is here. It checks to
see if the ``$response`` is actually null, and it says "Hey, *maybe* you
forgot to add a return statement somewhere in your controller?" You've probably
seen that message a couple of times.

In our case, since the controller *is* returning a Response, if we go back
and look at the events tab, we'll see that there's no ``kernel.view`` in
the list at the top. But below there's a "Not Called Listeners" section,
and there *is* one listener to ``kernel.view``, which comes from the SensioFrameworkExtraBundle,
that's not being executed.
