// Overrides the Google callback on the public landing page.
// Exchanges the ID token for a PHP session, then routes into /secure/.
window.handleCredentialResponse = function(res){
  (async()=>{
    try{
      const r = await fetch("/secure/api/auth.php?action=login", {
        method: "POST",
        credentials: "include",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id_token: res.credential })
      });
      const data = await r.json();
      if (!r.ok || data.ok === false) throw new Error(data.error || "login failed");
      window.location.href = "/secure/";
    }catch(e){
      console.error("Login exchange failed:", e);
      alert("Sign-in failed. Please try again.");
    }
  })();
};
