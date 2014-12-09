What about Sub Requests?
========================

So sometimes in TWIG templates you can find yourself in a position where
you need access to a variable and you don't have it. So let's pretend for
example that we're in our base template and we want to render some latest
tweets from our twitter account at the bottom of the page.

Now, we don't have any one controller that fuels this template. So there's no
way for us right here in our code to go out across Twitter's API and get those
latest tweets. So when you're in this position, the go to way to fix this is to
using the render function. Yes you can also make a TWIG extension, but I want to
show the render function here. 

So we can say render(controller), this will let us execute a totally different
controller. What we're going to do is render a new controller called
``_latestTweets``. Now I put the underscore in front of there not for any technical 
reason, just because this controller is going to return only a partial page. 
So when I have controllers that return partial pages I like having the underscore
in front of it. It reminds me that it's not going to be a full page.

So down here let's put some code in here for this,

public function _latestTweetsAction()

and of course we're not going to go across Twitter's API right this second. I'll
just copy in some fake tweets here. And then let's render the template. Easy enough!

And even though this is a partial page, we still always return a response object.
That's what we're doing here. So let's create this template, _latestTweets. 
And then this isn't really important, so I'll just paste something in. All I'm 
doing is just looping over these tweets and putting them in a list, pretty straight
forward.

Render will call out to that function, we'll get the content that's returned
and we should see that pop up at the bottom of our page. Scrolling down and there 
it is! Why do we show this? Why is this important to understanding the core of 
Symfony? Well if you look in the profiler, and go to the timeline you'll see
all the normal stuff, kernel.request and if we scroll down here we can see our
controller being run and our template being run but you notice this darker bar 
behind this. If you scroll down you'll see this second request, called a sub 
request. And this is a sub request that is executing the latesttweetsAction and
what you'll see that is important here is it actually is like a totally separate
request, it goes through all the same process it executes the kernel.request 
listeners you can see all the same listeners as before. It has the kernel.controller
event, it ultimately calls that controller. We need to call the Twig template,
it has kernel.response on the end. So it really is like we are handling two
totally separate request response cycles.

You can even if you want to click in to see the profiler just for this one sub
request, which is a really powerful thing to do. So now, remember back in our user
agent subscriber, because we're passing this $isMac attribute that means that's
going to be available in every single controller in the system. That's no different
with our sub request controller here, so I'm going to say $isMac, and let's
pass this into our template. We can print it out just to make sure this is 
working. 

We'll just print out a simple message, when we go back and refresh we see that 
on the top here we are on a Mac and on the bottom here, we are on a mac. Exactly
what we would expect. Here is where things get kinda crazy. I'm going to go back
to user agent subscriber and I'm going to add a little override so it's a little
easier for us to play with this ismac not a mac, since I am on one. What I'm going
to say if there's a query parameter, called ``notMac``, if that's set to some
value like, 1. Then let's set ``isMac`` to false. If you look up here if I refresh
again, I'm still on a Mac. Now I can set this notMac variable to 1 and it goes away.
It's an override to the system. As we'd expect it looks like we're not on a Mac.
Now, if I scroll down here and we look, the sub request does think we are on a Mac.
So something just short circuited in the system. Here's the issue, our user agent
subscriber is executed on the kernel.request event. But we're actually handling
two requests now, which means this is executed for the first request and it's 
also executed for the second request. The sub request goes through the same cycle.

I want to show you a little magic behind how sub requests are handled. So we're
going to leave this for a second and I'm going to go back to DinosaurController.
If we want to we can create a new sub request right here. I'm going to create
a new request object, I'm just going to set the _controller key on it. Set that
to appBundle, dinosaur, latestTweets. Then, I'm going to get out the HTTPKernel 
service. That's the same HttpKernel we've been talking about in previous chapters.
And it's actually a service in the container called http_kernel.

And I'm going to call that handle function on it, that the exact same handle
function we were looking at before. I'm going to pass it that request object.
Then I pass it a second argument here, which we're going to talk about more
in a second called sub requests. So don't worry about that yet. But see what
we're doing here, We're in the middle of $httpKernel->handle(). We're right
in the middle of the main request being done. If we want to we can create another
request and send it in httpKernel, now because I'm setting that _controller key
there, it knows which controller to render. That sub request is going to go through
that whole process and ultimately it's going to call the latest tweets. 

I'm not going to do anything with this response object, I'm just trying to prove
a point. If we go back here and refresh and click into the profiler, and go into
the timeline we now have two sub requests. One of them is the one I just created, 
if you scroll down you can see that, it's latestTweets. The other one is coming 
from render inside of our template. I did this, not because it's useful because
 I'm going to comment it out, but to show you that inside the ``base.html.twig`` 
 when we call render controller what's really happening inside of there is 
 something very similar to this. It's creating a brand new request object and
 setting the _controller key on it to whatever we're passing right there. So
 this is really important because UserAgentSubscriber, in fact all of our 
 listeners, are called on both requests. But the second time, the request is not
 the request we're thinking of. It's just some internal request that has the
 _controller set on it. That's why the first time this runs through and it reads
 the query parameters off of it, it reads the query parameters correctly. It reads
 the ``?notMac=1``. But the second time this is run for the sub request there are
 no query parameters. And this thing fails and on that request and on that controller
 isMac remains true. 
 
 So this is a really tricky thing. We went through the trouble of doing this because 
 when you have a sub request you need to not rely on the information from that
 request. It's not the real request, you need to not read headers or query parameters
 off of it because it's not exactly the request that you expect. Internally in 
 Symfony what it does it it duplicates the main request, so some information
 remains and some doesn't. That's why the headers on the main request get copied
 to the sub requests, which is why the user agent still looks like a Mac. But the
 query parameters aren't copied onto the sub request. 
 
 So whenever you are reading things off of the request, you need to ask yourself...
 do you feel lucky?... I mean... you need to do that only for the master request.
 You can't depend on that for the sub request. What we're going to do here is,
 and this is a really common thing to do in your listeners, you say "For what
 this listener is doing it should only run on that outer request, because it relies
 on information from that outer request." So what we're going to say is 
 if (!$event->isMasterRequest()), that's a method that all the event objects
 have on them we're just going to return. If this is a sub request, by the way
 how does it know if this is master or a sub request? That's because the second
 argument here. So when you call kernel handle it passes in "hey this is a sub
 request, this is not a master request". So this is our way of saying "look this
 is a sub request, don't run any of this. Only run it on the master request." .
 
When we refresh now we get a huge error, which makes sense. It says the 
_latestTweetsAction() requires that you provide a value for the $isMac argument
and there isn't one. And this is because when the sub request runs this function
isn't executed, so the sub request doesn't have an isMac attribute on it. So we
end up getting this error. You are probably wondering, but I need to know if this
is a mac on the sub request, what's the actual right way to read information off
of the master request?" The answer is really simple, just pass it in. The second
argument to the controller() here is an array of items that you want to make available
as arguments to your controller. So behind the scenes these are put into the attributes
of the sub request. So we can say userOnMac and then we can just read that off of
the master request. So, app.request.attributes.get('isMac'). So we'll read
the value off of the master request and pass it into the sub request. Inside of here
useronMac and then useronMac again. 

This time when we refresh, we still have the ?notMac=1, so it doesn't look like
we're on that kind of computer in the master request. If we scroll down, it says
not on a mac there as well because we're actually passing that information through.
We'll take this off and it looks like we're on a mac up top and it looks like we're
on a mac down there. 

So the big takeaway there is beware of sub requests and don't try to read request
information off of the sub request. This also ties into http caching and ESI which
is a topic we will cover later. If we're doing this correctly and you do want to
cache this little piece down here that is going to be super easy!
 
