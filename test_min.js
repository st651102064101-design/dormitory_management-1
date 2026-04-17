        document.addEventListener('DOMContentLoaded', function() {
          const card = document.getElementById('loginCard');
          Array.from(card.children).forEach(child => {
            if (child.id !== 'successOverlay') child.style.display = 'none';
          });
          const overlay = document.getElementById('successOverlay');
          overlay.style.display = 'flex';
          overlay.style.position = 'relative'; // so card uses its exact bounding box
          card.style.minHeight = '300px'; 
          card.classList.add('success');
         // ...
        });
